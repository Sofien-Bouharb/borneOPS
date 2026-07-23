<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
   // ForgotPasswordRequest
public function authorize(): bool { return true; }

public function rules(): array
{
    return ['email' => ['required', 'email']];
}
}
