<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class RfidBadgeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'identifier' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
