<?php

namespace App\Http\Requests;

use App\Services\UserService;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['nullable', 'string', 'in:' . implode(',', UserService::ASSIGNABLE_ROLES)],
            'organization_ids' => ['nullable', 'array'],
            'organization_ids.*' => ['integer', 'exists:organizations,id'],
        ];
    }

    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $validator) {
            $submitted = $validator->getData();

            if (($submitted['role'] ?? null) === 'Client' && empty($submitted['organization_ids'] ?? [])) {
                $validator->errors()->add(
                    'organization_ids',
                    'organization_ids is required when role is Client.'
                );
            }
        });
    }
}
