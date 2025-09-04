<?php

namespace App\Http\Requests;

class RsvpConfirmRequest
{
    public static function rules(): array
    {
        return [
            'confirm' => 'required|in:yes,no',
        ];
    }
}
