<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;
use App\Support\PhoneNormalizer;

/**
 * The corrected customer number the invoice is sent to again.
 */
class WhatsappResendRequest extends AbstractFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => PhoneNormalizer::normalize((string) $this->input('phone'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', PhoneNormalizer::rule()],
        ];
    }
}
