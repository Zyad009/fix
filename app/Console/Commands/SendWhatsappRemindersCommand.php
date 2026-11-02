<?php

namespace App\Console\Commands;

use App\Models\Tenant;
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
    protected $signature = 'whatsapp:reminders {--tenant= : Run for one tenant id only}';

    protected $description = 'Queue WhatsApp service and debt reminders for every center';

    public function handle(WhatsappReminderService $reminders): int
    {
        $tenants = Tenant::query()
            ->where('is_active', true)
            ->whereNotNull('whatsapp_device_id')
            ->where(fn (Builder $query) => $query->where('whatsapp_service_reminder_enabled', true)->orWhere('whatsapp_debt_reminder_enabled', true))
            ->when($this->option('tenant'), fn (Builder $query, string $id) => $query->whereKey($id))
            ->get()
            ->reject(fn (Tenant $tenant): bool => $tenant->isSubscriptionExpired());

        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $sent = tenantContext()->run($tenant, fn (): array => $reminders->run());
                $this->info("{$tenant->id} {$tenant->name}: {$sent['service']} service, {$sent['debt']} debt");
            } catch (Throwable $th) {
                $failed++;
                report($th);
                $this->error("{$tenant->id} {$tenant->name}: {$th->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
