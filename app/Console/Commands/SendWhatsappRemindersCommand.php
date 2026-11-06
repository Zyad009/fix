<?php

namespace App\Console\Commands;

use App\Models\MaintenanceCenter;
use App\Services\V1\WhatsappReminderService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Daily WhatsApp reminders (service date tomorrow, overdue debt) for every
 * center that has a device and switched at least one of them on. One center
 * failing never stops the others.
 */
class SendWhatsappRemindersCommand extends Command
{
    protected $signature = 'whatsapp:reminders {--maintenance-center= : Run for one maintenance center id only}';

    protected $description = 'Queue WhatsApp service and debt reminders for every center';

    public function handle(WhatsappReminderService $reminders): int
    {
        $maintenanceCenters = MaintenanceCenter::query()
            ->where('is_active', true)
            ->whereNotNull('whatsapp_device_id')
            ->where(fn (Builder $query) => $query->where('whatsapp_service_reminder_enabled', true)->orWhere('whatsapp_debt_reminder_enabled', true))
            ->when($this->option('maintenance-center'), fn (Builder $query, string $id) => $query->whereKey($id))
            ->get()
            ->reject(fn (MaintenanceCenter $maintenanceCenter): bool => $maintenanceCenter->isSubscriptionExpired());

        $failed = 0;

        foreach ($maintenanceCenters as $maintenanceCenter) {
            try {
                $sent = maintenanceCenterContext()->run($maintenanceCenter, fn (): array => $reminders->run());
                $this->info("{$maintenanceCenter->id} {$maintenanceCenter->name}: {$sent['service']} service, {$sent['debt']} debt");
            } catch (Throwable $th) {
                $failed++;
                report($th);
                $this->error("{$maintenanceCenter->id} {$maintenanceCenter->name}: {$th->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
