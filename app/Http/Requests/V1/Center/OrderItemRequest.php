<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\ProductKind;
use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

class OrderItemRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return self::lineRules();
        }

        return [
            'qty' => ['sometimes', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'unit_price' => ['sometimes', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'unit_cost' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'name' => ['sometimes', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::enum(ProductKind::class)],
        ];
    }

    /**
     * A catalog line (product_id) or a free line (name + unit_price).
     *
     * @return array<string, mixed>
     */
    public static function lineRules(string $prefix = ''): array
    {
        return [
            $prefix.'product_id' => ['nullable', 'integer', tenantExists('products', withoutTrashed: true)],
            $prefix.'name' => ['required_without:'.$prefix.'product_id', 'nullable', 'string', 'max:255'],
            $prefix.'kind' => ['nullable', Rule::enum(ProductKind::class)],
            $prefix.'qty' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999'],
            $prefix.'unit_price' => ['required_without:'.$prefix.'product_id', 'nullable', 'decimal:0,2', 'min:0', 'max:9999999999'],
            $prefix.'unit_cost' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999'],
        ];
    }
}
