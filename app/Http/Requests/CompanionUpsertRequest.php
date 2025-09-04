<?php

namespace App\Http\Requests;

class CompanionUpsertRequest
{
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'lastname' => 'nullable|string|max:255',
        ];
    }
}
