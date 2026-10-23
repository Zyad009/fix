<?php

namespace App\Services\V1;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class SettingsService
{
    public function show(): Tenant
    {
        return tenantContext()->tenant();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): Tenant
    {
        $tenant = tenantContext()->tenant();
        $tenant->update(Arr::only($data, ['name', 'phone', 'address', 'cashier_max_discount']));

        return $tenant;
    }

    public function updateLogo(UploadedFile $logo): Tenant
    {
        $tenant = tenantContext()->tenant();
        $disk = Storage::disk(config('filesystems.default'));

        $path = $logo->store("tenants/{$tenant->id}/logo", config('filesystems.default'));

        if ($tenant->logo_path) {
            $disk->delete($tenant->logo_path);
        }

        $tenant->update(['logo_path' => $path]);

        return $tenant;
    }
}
