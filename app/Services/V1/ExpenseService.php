<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Expense>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)
            ->with(['category:id,name', 'creator:id,name', 'branch:id,name'])
            ->latest('spent_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? config('paginate.med'));
    }

    /**
     * Filters are local calendar dates (spent_at is a DATE column).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Expense>
     */
    public function query(array $filters): Builder
    {
        return Expense::query()
            ->when(! ($filters['include_voided'] ?? false), fn (Builder $query) => $query->notVoided())
            ->when($filters['category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['method'] ?? null, fn (Builder $query, $method) => $query->where('method', $method))
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->where('spent_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->where('spent_at', '<=', $to));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total: float, count: int, by_category: list<array{category_id: int, name: string, total: float, count: int}>, by_method: list<array{method: array{label: string, value: string}, total: float}>}
     */
    public function summary(array $filters): array
    {
        $byCategory = $this->query($filters)
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->selectRaw('expenses.category_id, expense_categories.name, SUM(expenses.amount) AS total, COUNT(*) AS aggregate_count')
            ->groupBy('expenses.category_id', 'expense_categories.name')
            ->orderByDesc('total')
            ->toBase()
            ->get();

        $byMethod = $this->query($filters)
            ->selectRaw('method, SUM(amount) AS total')
            ->groupBy('method')
            ->toBase()
            ->get();

        return [
            'total' => Money::sum($byCategory->pluck('total')->map(fn ($total) => (string) $total))->toFloat(),
            'count' => (int) $byCategory->sum('aggregate_count'),
            'by_category' => $byCategory->map(fn ($row): array => [
                'category_id' => (int) $row->category_id,
                'name' => $row->name,
                'total' => formatNumber((string) $row->total),
                'count' => (int) $row->aggregate_count,
            ])->all(),
            'by_method' => $byMethod->map(fn ($row): array => [
                'method' => PaymentMethod::from($row->method)->response(),
                'total' => formatNumber((string) $row->total),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Expense
    {
        $expense = Expense::query()->create([
            ...Arr::only($data, ['category_id', 'amount', 'notes']),
            'method' => $data['method'] ?? PaymentMethod::Cash->value,
            'spent_at' => $data['spent_at'] ?? now(tenantContext()->tenant()->timezone)->toDateString(),
            'created_by' => actor()?->id,
        ]);

        return $this->load($expense);
    }

    public function void(Expense $expense, string $reason): Expense
    {
        return DB::transaction(function () use ($expense, $reason): Expense {
            $expense = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            $expense->markVoided($reason);

            $this->audit->record($expense, AuditEvent::Voided, ['amount' => $expense->amount], reason: $reason);

            return $this->load($expense);
        });
    }

    public function load(Expense $expense): Expense
    {
        return $expense->load(['category:id,name', 'creator:id,name', 'voidedBy:id,name', 'branch:id,name']);
    }
}
