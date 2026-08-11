<?php

namespace App\Http\Requests\Ocpp;

use Illuminate\Foundation\Http\FormRequest;

class StartTransactionEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ocpp_identifier' => ['required', 'string'],
            'connector_number' => ['required', 'integer', 'min:1'],
            'ocpp_transaction_id' => ['required', 'string'],
            'meter_start_wh' => ['required', 'integer', 'min:0'],
        ];
    }
}
