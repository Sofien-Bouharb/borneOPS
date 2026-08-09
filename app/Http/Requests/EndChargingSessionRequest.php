<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EndChargingSessionRequest extends FormRequest
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
     * Per Module 5 roadmap §11, completion accepts every terminal reason
     * code except equipment_unavailable — that code is reserved for
     * cancellation, since it describes a session that never actually
     * started charging. reason_detail is only required when reason_code is
     * 'other', matching the same check-constraint semantics enforced at the
     * database level in the charging_sessions migration.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'meter_stop_wh' => [
                'required',
                'integer',
                'min:0',
            ],
            'reason_code' => [
                'required',
                'in:user_requested,operator_requested,remote_stop,vehicle_disconnected,equipment_fault,power_loss,communication_loss,other',
            ],
            'reason_detail' => [
                'required_if:reason_code,other',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
