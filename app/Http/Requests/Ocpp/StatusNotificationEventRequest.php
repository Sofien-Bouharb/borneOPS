<?php

namespace App\Http\Requests\Ocpp;

use Illuminate\Foundation\Http\FormRequest;

class StatusNotificationEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ocpp_identifier' => ['required', 'string'],
            'connector_number' => ['nullable', 'integer', 'min:1'],
            'operational_status' => ['required', 'string', 'in:available,occupied,out_of_service,maintenance,disconnected,fault'],
        ];
    }
}
