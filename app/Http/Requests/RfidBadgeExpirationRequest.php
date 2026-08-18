<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class RfidBadgeExpirationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
