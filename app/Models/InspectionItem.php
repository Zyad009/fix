<?php

namespace App\Models;

use App\Enums\InspectionStatus;
use App\Models\Concerns\BelongsToMaintenanceCenter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One inspected checklist item. `label` is a snapshot taken when it was saved.
 *
 * @property int $id
 * @property int $inspection_id
 * @property string $section
 * @property string $item
 * @property string $label
 * @property InspectionStatus $status
 * @property string|null $note
 */
#[Fillable(['inspection_id', 'section', 'item', 'label', 'status', 'note'])]
class InspectionItem extends Model
{
    use BelongsToMaintenanceCenter;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InspectionStatus::class,
        ];
    }
}
