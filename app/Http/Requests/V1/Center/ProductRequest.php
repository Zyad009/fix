<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\ProductKind;
use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'category_id' => ['nullable', 'integer', tenantExists('categories')],
            'name' => [$required, 'string', 'max:255'],
            'kind' => [$required, Rule::enum(ProductKind::class)],
            'sale_price' => [$required, 'decimal:0,2', 'min:0', 'max:9999999999'],
            'cost_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'track_stock' => ['sometimes', 'boolean'],
            'image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
