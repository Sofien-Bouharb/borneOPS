<?php

namespace App\Http\Requests\Ocpp;

use Illuminate\Foundation\Http\FormRequest;

class MeterValuesEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ocpp_identifier' => ['required', 'string'],
            'ocpp_transaction_id' => ['required', 'string'],
            'meter_value_wh' => ['required', 'integer', 'min:0'],
        ];
    }
}
