<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;
use App\Models\CarModel;
use App\Support\PlateNormalizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class VehicleRequest extends AbstractFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('plate_number')) {
            $this->merge(['plate_normalized' => PlateNormalizer::normalize((string) $this->input('plate_number'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCreate = $this->isMethod('post');
        $vehicle = $this->route('vehicle');

        return [
            'customer_id' => [$isCreate ? 'required' : 'prohibited', 'integer', tenantExists('customers', withoutTrashed: true)],
            ...self::vehicleRules('', $isCreate),
            'plate_normalized' => [$isCreate ? 'required' : 'sometimes', 'string', 'min:2', tenantUnique('vehicles', 'plate_active')->ignore($vehicle)],
            'vin' => ['nullable', 'string', 'max:50', tenantUnique('vehicles', 'vin_active')->ignore($vehicle)],
        ];
    }

    /**
     * Field rules shared with inline vehicles on customer creation.
     *
     * @return array<string, mixed>
     */
    public static function vehicleRules(string $prefix = '', bool $isCreate = true): array
    {
        return [
            $prefix.'plate_number' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:30'],
            $prefix.'vin' => ['nullable', 'string', 'max:50'],
            $prefix.'car_make_id' => ['nullable', 'integer', Rule::exists('car_makes', 'id')->where('is_active', true)],
            $prefix.'car_model_id' => ['nullable', 'integer', Rule::exists('car_models', 'id')->where('is_active', true)],
            $prefix.'year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            $prefix.'color' => ['nullable', 'string', 'max:50'],
            $prefix.'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $modelId = $this->input('car_model_id');

            if (! $modelId || $validator->errors()->isNotEmpty()) {
                return;
            }

            $makeId = $this->input('car_make_id', $this->route('vehicle')?->car_make_id);

            if ((int) CarModel::query()->whereKey($modelId)->value('car_make_id') !== (int) $makeId) {
                $validator->errors()->add('car_model_id', __('messages.car_model_mismatch'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['plate_normalized' => __('validation.attributes.plate_number')];
    }
}
