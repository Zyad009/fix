<?php

namespace App\Services\V1;

use App\Models\MaintenanceCenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class SettingsService
{
    public function show(): MaintenanceCenter
    {
        return maintenanceCenterContext()->maintenanceCenter();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): MaintenanceCenter
    {
        $maintenanceCenter = maintenanceCenterContext()->maintenanceCenter();
        $maintenanceCenter->update(Arr::only($data, ['name', 'phone', 'address', 'cashier_max_discount', 'whatsapp_invoice_enabled', 'whatsapp_payment_enabled', 'whatsapp_service_reminder_enabled', 'whatsapp_debt_reminder_enabled', 'debt_reminder_days']));

        return $maintenanceCenter;
    }

    public function updateLogo(UploadedFile $logo): MaintenanceCenter
    {
        $maintenanceCenter = maintenanceCenterContext()->maintenanceCenter();
        $disk = Storage::disk(config('filesystems.default'));

        $path = $logo->store("maintenance-centers/{$maintenanceCenter->id}/logo", config('filesystems.default'));

        if ($maintenanceCenter->logo_path) {
            $disk->delete($maintenanceCenter->logo_path);
        }

        $maintenanceCenter->update(['logo_path' => $path]);

        return $maintenanceCenter;
    }
}
