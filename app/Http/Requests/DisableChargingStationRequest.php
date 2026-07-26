<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DisableChargingStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
