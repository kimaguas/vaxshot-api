<?php

namespace App\Http\Requests;

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
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role'     => 'required|in:admin,manager,staff,viewer',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Name is required',
            'username.required'  => 'Username is required',
            'username.unique'    => 'Username already exists',
            'email.required'     => 'Email is required',
            'email.unique'       => 'Email already exists',
            'password.required'  => 'Password is required',
            'password.min'       => 'Password must be at least 6 characters',
            'password.confirmed' => 'Passwords do not match',
            'role.required'      => 'Role is required',
            'role.in'            => 'Role must be admin, manager, staff or viewer',
        ];
    }
}