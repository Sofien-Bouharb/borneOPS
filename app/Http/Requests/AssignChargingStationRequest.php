<?php

namespace App\Http\Requests;

use App\Models\ChargingStation;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class AssignChargingStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['present', 'nullable', 'integer', 'exists:sites,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

public function withValidator(ValidatorContract $validator): void
{
    $validator->after(function (ValidatorContract $validator) {
        /** @var ChargingStation|null $station */
        $station = $this->route('charging_station');

        $submitted = $validator->getData();
        $siteId = array_key_exists('site_id', $submitted) ? $submitted['site_id'] : null;

        if (is_null($siteId) && $station && $station->administrative_status !== 'commissioning') {
            $validator->errors()->add(
                'site_id',
                'A station can only be unassigned while it is in commissioning.'
            );
        }
    });
}
}
