<?php

namespace App\Http\Requests;

class GuestUpdateRequest
{
    public static function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'lastname' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email',
            'phone' => 'sometimes|nullable|string|max:30',
            'enable_companion' => 'sometimes|boolean',
            'confirm' => 'sometimes|in:pending,yes,no',
            'notes' => 'sometimes|nullable|string',
            'companion' => 'sometimes|array',
            'companion.name' => 'sometimes|nullable|string|max:255',
            'companion.lastname' => 'sometimes|nullable|string|max:255',
        ];
    }
}
