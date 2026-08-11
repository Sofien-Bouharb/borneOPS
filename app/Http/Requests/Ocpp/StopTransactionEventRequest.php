<?php

namespace App\Http\Requests\Ocpp;

use Illuminate\Foundation\Http\FormRequest;

class StopTransactionEventRequest extends FormRequest
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
            'meter_stop_wh' => ['required', 'integer', 'min:0'],
            'reason_code' => ['required', 'string', 'in:user_requested,operator_requested,remote_stop,vehicle_disconnected,equipment_fault,power_loss,communication_loss,other'],
        ];
    }
}
