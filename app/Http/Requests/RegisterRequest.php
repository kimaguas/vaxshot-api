<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role'     => 'required|in:admin,manager,staff,viewer',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Name is required',
            'email.required'     => 'Email is required',
            'email.email'        => 'Email must be valid',
            'email.unique'       => 'Email already exists',
            'password.required'  => 'Password is required',
            'password.min'       => 'Password must be at least 6 characters',
            'password.confirmed' => 'Passwords do not match',
            'role.required'      => 'Role is required',
            'role.in'            => 'Role must be admin, manager, staff or viewer',
        ];
    }
}