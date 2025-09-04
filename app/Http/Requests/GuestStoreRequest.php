<?php

namespace App\Http\Requests;

class GuestStoreRequest
{
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:30',
            'enable_companion' => 'boolean',
            'notes' => 'nullable|string',
            'companion' => 'sometimes|array',
            'companion.name' => 'sometimes|nullable|string|max:255',
            'companion.lastname' => 'sometimes|nullable|string|max:255',
        ];
    }
}
