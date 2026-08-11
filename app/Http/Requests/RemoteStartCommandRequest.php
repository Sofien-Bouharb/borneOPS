<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoteStartCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_tag' => ['required', 'string'],
        ];
    }
}
