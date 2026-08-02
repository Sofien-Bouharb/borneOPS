<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConnectorRequest extends FormRequest
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
        $station = $this->route('station');

        return [
            'connector_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('connectors', 'connector_number')
                    ->where('charging_station_id', $station?->id),
            ],
            'standard' => ['required', 'in:ccs,type2,chademo'],
            'max_power_kw' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $station = $this->route('station');
            $maxPowerKw = $this->input('max_power_kw');

            if ($station && is_numeric($maxPowerKw) && (float) $maxPowerKw > (float) $station->power_kw) {
                $validator->errors()->add(
                    'max_power_kw',
                    'La puissance du connecteur ne peut pas dépasser la puissance de la borne.'
                );
            }
        });
    }
}
