<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Exceptions\BusinessRuleException;
use App\Models\Inspection;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * The technical inspection of an invoice. It follows the invoice's edit
 * rules: a cancelled or opening-balance invoice is locked, and a completed
 * one needs `orders.edit_completed`.
 */
class InspectionService
{
    public function __construct(
        private readonly OrderGuard $guard,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * The fixed checklist with labels in the request language.
     *
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string}>}>
     */
    public function checklist(): array
    {
        $sections = [];

        foreach (config('inspection.sections') as $section => $keys) {
            $sections[] = [
                'key' => $section,
                'label' => __('messages.inspection_sections')[$section] ?? $section,
                'items' => array_map(fn (string $key): array => [
                    'key' => $key,
                    'label' => __('messages.inspection_items')[$key] ?? $key,
                ], $keys),
            ];
        }

        return $sections;
    }

    public function find(Order $order): ?Inspection
    {
        $inspection = $order->inspection()->with(['items', 'inspector:id,name'])->first();
        $inspection?->setRelation('order', $order->loadMissing('latestInspectionWhatsapp'));

        return $inspection;
    }

    public function findOrFail(Order $order): Inspection
    {
        return $this->find($order) ?? throw new BusinessRuleException(__('messages.inspection_not_found'), 404);
    }

    /**
     * Saves the whole inspection: the items sent replace the stored ones.
     *
     * @param  array{items: list<array{item: string, status: string, note?: ?string}>, diagnostic_result?: ?string, notes?: ?string}  $data
     */
    public function save(Order $order, array $data): Inspection
    {
        return DB::transaction(function () use ($order, $data): Inspection {
            $order = $this->guard->lockEditable($order);
            $inspection = $this->find($order);
            $old = $inspection ? $this->auditValues($inspection) : null;

            $inspection = Inspection::query()->updateOrCreate(['order_id' => $order->id], [
                'diagnostic_result' => $data['diagnostic_result'] ?? null,
                'notes' => $data['notes'] ?? null,
                'inspected_by' => actor()?->id,
            ]);

            $inspection->items()->delete();
            $sections = Inspection::checklistSections();

            foreach ($data['items'] as $line) {
                $inspection->items()->create([
                    'section' => $sections[$line['item']],
                    'item' => $line['item'],
                    'label' => __('messages.inspection_items', [], 'ar')[$line['item']] ?? $line['item'],
                    'status' => $line['status'],
                    'note' => $line['note'] ?? null,
                ]);
            }

            $inspection = $this->findOrFail($order);

            if ($order->isCompleted()) {
                $this->audit->record($order, AuditEvent::Updated, ['inspection' => $old], ['inspection' => $this->auditValues($inspection)]);
            }

            return $inspection;
        });
    }

    public function destroy(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = $this->guard->lockEditable($order);
            $inspection = $this->findOrFail($order);

            if ($order->isCompleted()) {
                $this->audit->record($order, AuditEvent::Updated, ['inspection' => $this->auditValues($inspection)], ['inspection' => null]);
            }

            $inspection->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Inspection $inspection): array
    {
        return [
            ...$inspection->summary(),
            'items' => $inspection->items->mapWithKeys(fn ($item): array => [$item->item => $item->status->value])->all(),
        ];
    }
}
