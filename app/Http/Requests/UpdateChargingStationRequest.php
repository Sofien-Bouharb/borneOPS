<?php

namespace App\Http\Requests;

use App\Models\ChargingStation;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChargingStationRequest extends FormRequest
{
    protected const SENSITIVE_FIELDS = ['reference', 'serial_number', 'ocpp_identifier', 'ocpp_version'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var ChargingStation|null $station */
        $station = $this->route('station');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'model' => ['sometimes', 'required', 'string', 'max:255'],
            'manufacturer' => ['sometimes', 'required', 'string', 'max:255'],
            'firmware_version' => ['nullable', 'string', 'max:50'],
            'power_kw' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],

            'reference' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('charging_stations', 'reference')->ignore($station?->id),
            ],
            'serial_number' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('charging_stations', 'serial_number')->ignore($station?->id),
            ],
            'ocpp_identifier' => [
                'sometimes', 'nullable', 'string', 'max:255',
                Rule::unique('charging_stations', 'ocpp_identifier')->ignore($station?->id),
            ],
            'ocpp_version' => [
                'sometimes', 'required', 'in:1.6,2.0.1',
            ],
            'reason' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected const FIELD_LABELS_FR = [
        'reference' => 'Référence',
        'serial_number' => 'Numéro de série',
        'ocpp_identifier' => 'Identifiant OCPP',
        'ocpp_version' => 'Version OCPP',
    ];

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            /** @var ChargingStation|null $station */
            $station = $this->route('station');

            if (!$station || $station->administrative_status === 'commissioning') {
                return;
            }

            $submitted = $validator->getData();

            foreach (self::SENSITIVE_FIELDS as $field) {
                if (array_key_exists($field, $submitted)) {
                    $label = self::FIELD_LABELS_FR[$field] ?? $field;
                    $validator->errors()->add(
                        $field,
                        "Le champ « {$label} » ne peut être modifié qu'en phase de mise en service."
                    );
                }
            }
        });
    }
}
