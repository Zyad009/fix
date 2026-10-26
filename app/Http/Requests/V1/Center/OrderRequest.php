<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Validation\Validator;

class OrderRequest extends AbstractFormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('customer')) && $this->has('customer.phone')) {
            $this->merge(['customer' => [...$this->input('customer'), 'phone' => PhoneNormalizer::normalize((string) $this->input('customer.phone'))]]);
        }
    }

    /**
     * Create: an existing `vehicle_id`, or a new/known `customer` + `vehicle`
     * typed on the invoice screen. `complete` (+ `payments`) turns it into an
     * invoice in the same call.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCreate = $this->isMethod('post');

        return [
            'vehicle_id' => [$isCreate ? 'required_without:vehicle' : 'prohibited', 'nullable', 'integer', tenantExists('vehicles', withoutTrashed: true)],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'technician_id' => ['nullable', 'integer'],
            'discount' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'next_service_odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'next_service_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => [$isCreate ? 'sometimes' : 'prohibited', 'array', 'max:100'],
            ...OrderItemRequest::lineRules('items.*.'),
            ...($isCreate ? $this->inlineRules() : ['vehicle' => ['prohibited'], 'customer' => ['prohibited']]),
            'complete' => [$isCreate ? 'sometimes' : 'prohibited', 'boolean'],
            ...CompleteOrderRequest::paymentRules([$isCreate ? 'prohibited_unless:complete,true' : 'prohibited']),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $modelId = $this->input('vehicle.car_model_id');

            if ($modelId && $validator->errors()->isEmpty() && ! VehicleRequest::modelBelongsToMake($modelId, $this->input('vehicle.car_make_id'))) {
                $validator->errors()->add('vehicle.car_model_id', __('messages.car_model_mismatch'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function inlineRules(): array
    {
        $vehicleRules = array_map(
            fn (array $rules): array => ['exclude_without:vehicle', ...$rules],
            VehicleRequest::vehicleRules('vehicle.'),
        );

        return [
            'vehicle' => ['sometimes', 'array', 'prohibits:vehicle_id'],
            ...$vehicleRules,
            'customer' => ['required_with:vehicle', 'exclude_without:vehicle', 'array'],
            'customer.id' => ['exclude_without:vehicle', 'nullable', 'integer', tenantExists('customers', withoutTrashed: true)],
            'customer.name' => ['exclude_without:vehicle', 'required_without:customer.id', 'nullable', 'string', 'max:255'],
            'customer.phone' => ['exclude_without:vehicle', 'required_without:customer.id', 'nullable', 'string', 'min:7', 'max:15'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vehicle.plate_number' => __('validation.attributes.plate_number'),
        ];
    }
}
