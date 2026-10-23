<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;

class ExpenseCategoryRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', tenantUnique('expense_categories', 'name')->ignore($this->route('expense_category'))],
        ];
    }
}
