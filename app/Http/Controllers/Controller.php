<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use App\Models\AuditLog;

class Controller extends BaseController
{
    protected function audit($data = []) {
        AuditLog::create([
            'source' => 'frontend',
            ...$data
        ]);
    }

    protected function respondError($message = 'Internal Server Error', $status = 500, $extra = []) {
        return response()->json(array_merge([
            'message' => $message,
        ], $extra), $status);
    }
}
