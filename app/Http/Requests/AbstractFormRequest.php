<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

abstract class AbstractFormRequest extends FormRequest
{
    /**
     * Rules for a login username: optional, unique across all centers (login
     * happens before any center is known), and never shaped like an email.
     *
     * @return list<mixed>
     */
    protected function usernameRules(mixed $ignoreUser = null): array
    {
        return ['nullable', 'string', 'min:3', 'max:50', 'alpha_dash:ascii', Rule::unique('users', 'username')->ignore($ignoreUser)];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reject a completely empty payload. Index/filter requests override this
     * with `false`, since "no filters" is a valid listing.
     */
    protected function requireAtLeastOneField(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->requireAtLeastOneField() && $this->isEmptyRequest()) {
                $validator->errors()->add('general', __('messages.at_least_one_field'));
            }
        });
    }

    protected function isEmptyRequest(): bool
    {
        return collect($this->all())
            ->filter(fn (mixed $value): bool => ! is_null($value) && ! (is_string($value) && trim($value) === ''))
            ->isEmpty();
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 0,
                'code' => 422,
                'message' => $validator->errors()->first(),
                'data' => null,
            ], 422)
        );
    }
}
