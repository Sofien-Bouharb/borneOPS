<?php

namespace App\Http\Requests\Ocpp;

use Illuminate\Foundation\Http\FormRequest;

class VerifyStationCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ocpp_identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
