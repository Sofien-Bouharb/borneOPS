<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConnectorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $connector = $this->route('connector');

        return [
            'connector_number' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::unique('connectors', 'connector_number')
                    ->where('charging_station_id', $connector?->charging_station_id)
                    ->ignore($connector?->id),
            ],
            'standard' => ['sometimes', 'in:ccs,type2,chademo'],
            'max_power_kw' => ['sometimes', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $connector = $this->route('connector');

            if (! $connector || ! $this->has('max_power_kw')) {
                return;
            }

            $maxPowerKw = $this->input('max_power_kw');
            $station = $connector->chargingStation;

            if ($station && is_numeric($maxPowerKw) && (float) $maxPowerKw > (float) $station->power_kw) {
                $validator->errors()->add(
                    'max_power_kw',
                    'La puissance du connecteur ne peut pas dépasser la puissance de la borne.'
                );
            }
        });
    }
}
