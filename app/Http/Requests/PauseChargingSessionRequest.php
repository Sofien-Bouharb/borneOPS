<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PauseChargingSessionRequest extends FormRequest
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
     * Pausing a session carries no request body per the Module 5 roadmap
     * (§17) — only the meter_start_wh at start and meter_stop_wh at end are
     * client-supplied meter readings. This class exists to keep the same
     * one-Form-Request-per-action pattern used across the whole project,
     * even though its rule set is empty.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
