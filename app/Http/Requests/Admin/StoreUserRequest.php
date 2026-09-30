<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An admin creating a user of any type; farmers and buyers also need their profile fields.
 */
class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone'         => 'required|string|unique:users,phone',
            'email'         => 'nullable|email|unique:users,email',
            'password'      => 'required|string|min:6',
            'user_type'     => ['required', Rule::in(['admin', 'farmer', 'buyer'])],
            'status'        => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'blocked'])],
            'first_name'    => 'required_if:user_type,farmer,buyer|string',
            'second_name'   => 'required_if:user_type,farmer,buyer|string',
            'address'       => 'required_if:user_type,farmer,buyer|string',
            'city'          => 'required_if:user_type,farmer,buyer|string',
            'business_name' => 'required_if:user_type,buyer|string',
        ];
    }
}
