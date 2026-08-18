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
            'meter_start_wh' => ['required', 'integer', 'min:0'],
            'identifier' => ['nullable', 'string'],
        ];
    }
}
