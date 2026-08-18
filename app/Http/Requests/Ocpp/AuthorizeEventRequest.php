<?php
namespace App\Http\Requests\Ocpp;
use Illuminate\Foundation\Http\FormRequest;
class AuthorizeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'ocpp_identifier' => ['required', 'string'],
            'identifier' => ['required', 'string'],
        ];
    }
}
