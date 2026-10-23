<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;
use App\Support\PhoneNormalizer;
use App\Support\PlateNormalizer;

class CustomerRequest extends AbstractFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => PhoneNormalizer::normalize((string) $this->input('phone'))]);
        }

        if (is_array($this->input('vehicles'))) {
            $this->merge(['vehicles' => array_map(
                fn (mixed $vehicle): mixed => is_array($vehicle)
                    ? [...$vehicle, 'plate_normalized' => PlateNormalizer::normalize((string) ($vehicle['plate_number'] ?? ''))]
                    : $vehicle,
                $this->input('vehicles'),
            )]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'phone' => [$required, 'string', 'min:7', 'max:15', tenantUnique('customers', 'phone_active')->ignore($this->route('customer'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'vehicles' => [$this->isMethod('post') ? 'sometimes' : 'prohibited', 'array', 'max:10'],
            ...VehicleRequest::vehicleRules('vehicles.*.'),
            'vehicles.*.plate_normalized' => ['required', 'string', 'min:2', 'distinct', tenantUnique('vehicles', 'plate_active')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['vehicles.*.plate_normalized' => __('validation.attributes.plate_number')];
    }
}
