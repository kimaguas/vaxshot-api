<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'sometimes|string|max:255',
            'email'    => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'password' => 'sometimes|string|min:6|confirmed',
            'role'     => 'sometimes|in:admin,manager,staff,viewer',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'       => 'Email already exists',
            'password.min'       => 'Password must be at least 6 characters',
            'password.confirmed' => 'Passwords do not match',
            'role.in'            => 'Role must be admin, manager, staff or viewer',
        ];
    }
}