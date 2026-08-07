<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelChargingSessionRequest extends FormRequest
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
     * Per Module 5 roadmap §11, cancellation only accepts a narrower set of
     * reason codes than completion, since a cancelled session (by
     * definition, per §1) was always in the pending state and never
     * actually started charging. reason_detail is required only when
     * reason_code is 'other'.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason_code' => [
                'required',
                'in:user_requested,operator_requested,equipment_unavailable,other',
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
