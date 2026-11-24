<?php

namespace App\Services\V1;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RoutineService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Routine services: a name and an on/off switch, no price. Lists are ordered
 * by usage (lines on non-cancelled invoices) and carry a suggested price: the
 * most frequent price of the last 3 months (newest wins a tie), else the
 * last price ever used.
 */
class RoutineServiceService
{
    public const SUGGESTION_MONTHS = 3;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, RoutineService>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $services = $this->withUsage(RoutineService::query())
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where('name', 'like', "%{$q}%"))
            ->when(isset($filters['is_active']), fn (Builder $query) => $query->where('is_active', (bool) $filters['is_active']))
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? config('paginate.max'));

        $this->attachSuggestedPrices($services->getCollection());

        return $services;
    }

    public function load(RoutineService $service): RoutineService
    {
        $service = $this->withStats($service);
        $service->prices = $this->recentPrices([$service->id])
            ->sortBy([['uses', 'desc'], ['last_used_at', 'desc']])
            ->map(fn (OrderItem $row): array => ['price' => formatNumber($row->unit_price), 'uses' => (int) $row->uses])
            ->values()
            ->all();

        return $service;
    }

    /**
     * Invoice lines that used the service, newest first, across all branches.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, OrderItem>
     */
    public function history(RoutineService $service, array $filters): LengthAwarePaginator
    {
        return $this->usedLines()
            ->where('routine_service_id', $service->id)
            ->with(['order' => fn ($query) => $query->withoutGlobalScope('branch')->with(['branch:id,name', 'customer:id,name', 'vehicle:id,plate_number'])])
            ->latest('id')
            ->paginate($filters['per_page'] ?? config('paginate.med'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): RoutineService
    {
        return $this->withStats(RoutineService::query()->create([...Arr::only($data, ['name']), 'created_by' => actor()?->id]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RoutineService $service, array $data): RoutineService
    {
        $service->update(Arr::only($data, ['name']));

        return $this->withStats($service);
    }

    public function toggle(int $serviceId): RoutineService
    {
        $service = RoutineService::query()->findOrFail($serviceId);
        $service->update(['is_active' => ! $service->is_active]);

        return $this->withStats($service);
    }

    /**
     * Only a service no invoice ever used (cancelled ones included) can go.
     */
    public function destroy(RoutineService $service): void
    {
        if (OrderItem::query()->where('routine_service_id', $service->id)->exists()) {
            throw new BusinessRuleException(__('messages.routine_service_in_use'), 409);
        }

        $service->delete();
    }

    /**
     * The service re-read with its usage count, last use and suggested price.
     */
    private function withStats(RoutineService $service): RoutineService
    {
        $service = $this->withUsage(RoutineService::query())->findOrFail($service->id);
        $this->attachSuggestedPrices(collect([$service]));

        return $service;
    }

    /**
     * @param  Builder<RoutineService>  $query
     * @return Builder<RoutineService>
     */
    private function withUsage(Builder $query): Builder
    {
        $onActiveOrders = fn ($query) => $query->whereIn('order_id', $this->activeOrderIds());

        return $query
            ->withCount(['orderItems as usage_count' => $onActiveOrders])
            ->withMax(['orderItems as last_used_at' => $onActiveOrders], 'created_at');
    }

    /**
     * @param  Collection<int, RoutineService>  $services
     */
    private function attachSuggestedPrices(Collection $services): void
    {
        $ids = $services->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        $suggested = $this->recentPrices($ids)
            ->groupBy('routine_service_id')
            ->map(fn (Collection $rows) => $rows->sortBy([['uses', 'desc'], ['last_used_at', 'desc']])->first()->unit_price);

        $missing = array_values(array_diff($ids, $suggested->keys()->all()));

        if ($missing !== []) {
            $lastLineIds = $this->usedLines()->whereIn('routine_service_id', $missing)
                ->groupBy('routine_service_id')
                ->selectRaw('MAX(id) as id');

            OrderItem::query()->whereIn('id', $lastLineIds)->get(['routine_service_id', 'unit_price'])
                ->each(fn (OrderItem $line) => $suggested->put($line->routine_service_id, $line->unit_price));
        }

        $services->each(fn (RoutineService $service) => $service->suggested_price = $suggested->get($service->id));
    }

    /**
     * Each price used in the suggestion window with how often and how
     * recently, per service.
     *
     * @param  list<int>  $ids
     * @return Collection<int, OrderItem>
     */
    private function recentPrices(array $ids): Collection
    {
        return $this->usedLines()
            ->whereIn('routine_service_id', $ids)
            ->where('created_at', '>=', Carbon::now()->subMonths(self::SUGGESTION_MONTHS))
            ->groupBy('routine_service_id', 'unit_price')
            ->selectRaw('routine_service_id, unit_price, COUNT(*) as uses, MAX(created_at) as last_used_at')
            ->get();
    }

    /**
     * @return Builder<OrderItem>
     */
    private function usedLines(): Builder
    {
        return OrderItem::query()->whereIn('order_id', $this->activeOrderIds());
    }

    /**
     * @return Builder<Order>
     */
    private function activeOrderIds(): Builder
    {
        return Order::query()->withoutGlobalScope('branch')->where('status', '!=', OrderStatus::Cancelled)->select('id');
    }
}
