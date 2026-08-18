<?php
namespace App\Http\Requests\Ocpp;
use Illuminate\Foundation\Http\FormRequest;
class StartTransactionExternalEventRequest extends FormRequest
{
public function authorize(): bool
    {
return true;
    }
public function rules(): array
    {
return [
'ocpp_identifier' => ['required', 'string'],
'evse_id' => ['required', 'integer', 'min:1'],
'connector_id' => ['nullable', 'integer', 'min:1'],
'external_transaction_id' => ['required', 'string'],
'meter_start_wh' => ['required', 'integer', 'min:0'],
'identifier' => ['nullable', 'string'],
        ];
    }
}
