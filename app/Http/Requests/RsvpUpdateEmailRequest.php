<?php

namespace App\Http\Requests;

class RsvpUpdateEmailRequest
{
    public static function rules(): array
    {
        return [
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:30',
        ];
    }
}
