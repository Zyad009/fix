<?php

namespace App\Http\Requests;

/**
 * Base for list/filter requests: an empty query string is a valid listing.
 */
abstract class IndexRequest extends AbstractFormRequest
{
    protected function requireAtLeastOneField(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'format' => ['nullable', 'in:json,excel'],
            ...$this->filters(),
        ];
    }

    /**
     * Endpoint-specific filter rules.
     *
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [];
    }

    /**
     * Shared `from` / `to` (local Y-m-d) period filter rules.
     *
     * @return array<string, mixed>
     */
    protected function periodRules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
