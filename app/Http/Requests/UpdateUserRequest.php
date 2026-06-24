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

    protected function prepareForValidation(): void
    {
        if (empty($this->input('password'))) {
            $this->replace($this->except(['password', 'password_confirmation']));
        }
    }

    public function rules(): array
    {
        return [
            'name'     => 'sometimes|string|max:255',
            'username' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($this->route('user')),
            ],
            'email'    => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'password' => 'sometimes|string|min:6|confirmed',
            'role'          => 'sometimes|in:admin,manager,staff,viewer,sales_rep',
            'permissions'   => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
            'area_code_id'  => 'nullable|exists:area_codes,id',
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique'    => 'Username already exists',
            'email.unique'       => 'Email already exists',
            'password.min'       => 'Password must be at least 6 characters',
            'password.confirmed' => 'Passwords do not match',
            'role.in'            => 'Role must be admin, manager, staff, viewer, or sales_rep',
        ];
    }
}