<?php

namespace App\Services\V1;

use App\Models\CarMake;
use App\Models\CarModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

/**
 * Platform-wide makes/models: the admin curates them, centers read them.
 */
class CarCatalogService
{
    /**
     * @return Collection<int, CarMake>
     */
    public function list(bool $activeOnly = true): Collection
    {
        return CarMake::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->with(['models' => fn ($query) => $query
                ->when($activeOnly, fn ($query) => $query->where('is_active', true))
                ->orderBy('name_ar')])
            ->orderBy('name_ar')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeMake(array $data): CarMake
    {
        return CarMake::query()->create(Arr::only($data, ['name_ar', 'name_en']))->load('models');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMake(CarMake $make, array $data): CarMake
    {
        $make->update(Arr::only($data, ['name_ar', 'name_en']));

        return $make->load('models');
    }

    public function toggleMake(int $makeId): CarMake
    {
        $make = CarMake::query()->findOrFail($makeId);
        $make->update(['is_active' => ! $make->is_active]);

        return $make->load('models');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeModel(CarMake $make, array $data): CarModel
    {
        return $make->models()->create(Arr::only($data, ['name_ar', 'name_en']));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateModel(CarModel $model, array $data): CarModel
    {
        $model->update(Arr::only($data, ['name_ar', 'name_en']));

        return $model;
    }

    public function toggleModel(int $modelId): CarModel
    {
        $model = CarModel::query()->findOrFail($modelId);
        $model->update(['is_active' => ! $model->is_active]);

        return $model;
    }
}
