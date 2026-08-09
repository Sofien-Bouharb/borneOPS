<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResumeChargingSessionRequest extends FormRequest
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
     * Resuming carries no request body, same reasoning as
     * PauseChargingSessionRequest. Resume reuses the charging_sessions.pause
     * permission at the route level per the Module 5 roadmap (§16) — this
     * class is still separate to match the project's one-request-per-action
     * convention.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
