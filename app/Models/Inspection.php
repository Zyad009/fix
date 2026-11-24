<?php

namespace App\Models;

use App\Enums\InspectionStatus;
use App\Models\Concerns\BelongsToMaintenanceCenter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The technical inspection of an invoice; one per order.
 *
 * @property int $id
 * @property int $order_id
 * @property string|null $diagnostic_result
 * @property string|null $notes
 * @property int|null $inspected_by
 */
#[Fillable(['order_id', 'diagnostic_result', 'notes', 'inspected_by'])]
class Inspection extends Model
{
    use BelongsToMaintenanceCenter;

    /**
     * The checklist sections with this inspection's results, in display order.
     * An item dropped from the checklist since it was saved still shows, in its
     * original section, under the label snapshot taken when it was saved.
     *
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string, status: ?InspectionStatus, note: ?string}>}>
     */
    public function report(): array
    {
        $results = $this->items->keyBy('item');
        $sections = [];

        foreach (config('inspection.sections') as $section => $keys) {
            $sections[$section] = [
                'key' => $section,
                'label' => __('messages.inspection_sections')[$section] ?? $section,
                'items' => array_map(fn (string $key): array => [
                    'key' => $key,
                    'label' => __('messages.inspection_items')[$key] ?? $results->get($key)?->label ?? $key,
                    'status' => $results->get($key)?->status,
                    'note' => $results->get($key)?->note,
                ], $keys),
            ];
        }

        foreach ($results->diffKeys(static::checklistSections()) as $retired) {
            $sections[$retired->section] ??= ['key' => $retired->section, 'label' => __('messages.inspection_sections')[$retired->section] ?? $retired->section, 'items' => []];
            $sections[$retired->section]['items'][] = ['key' => $retired->item, 'label' => $retired->label, 'status' => $retired->status, 'note' => $retired->note];
        }

        return array_values($sections);
    }

    /**
     * Item count per result; `not_checked` counts checklist items with no result.
     *
     * @return array{ok: int, attention: int, urgent: int, not_checked: int}
     */
    public function summary(): array
    {
        $counts = $this->items->countBy(fn (InspectionItem $item): string => $item->status->value);
        $checklist = static::checklistSections();

        return [
            'ok' => $counts->get(InspectionStatus::Ok->value, 0),
            'attention' => $counts->get(InspectionStatus::Attention->value, 0),
            'urgent' => $counts->get(InspectionStatus::Urgent->value, 0),
            'not_checked' => count($checklist) - $this->items->whereIn('item', array_keys($checklist))->count(),
        ];
    }

    /**
     * Every checklist item key => its section.
     *
     * @return array<string, string>
     */
    public static function checklistSections(): array
    {
        $sections = [];

        foreach (config('inspection.sections') as $section => $keys) {
            $sections += array_fill_keys($keys, $section);
        }

        return $sections;
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<InspectionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class, 'inspection_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
