<?php

namespace App\Http\Requests;

class GuestUpdateByTokenRequest
{
    public static function rules(): array
    {
        return [
            'confirm' => 'nullable|in:pending,yes,no',
            'companion.name' => 'nullable|string|max:255',
            'companion.lastname' => 'nullable|string|max:255',
        ];
    }
}
