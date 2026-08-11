<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:Hard,Soft'],
        ];
    }
}
