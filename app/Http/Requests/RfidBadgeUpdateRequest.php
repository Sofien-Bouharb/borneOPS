<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class RfidBadgeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
