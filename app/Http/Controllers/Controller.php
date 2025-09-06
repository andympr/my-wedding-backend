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

    /**
     * Remove timestamps and null values from arrays before logging.
     */
    protected function filterForAuditArray(array $arr): array
    {
        unset($arr['created_at'], $arr['updated_at']);
        foreach ($arr as $k => $v) {
            if ($v === null) unset($arr[$k]);
        }
        return $arr;
    }

    protected function toAuditJson(array $arr): string
    {
        return json_encode($this->filterForAuditArray($arr), JSON_UNESCAPED_UNICODE);
    }

    protected function respondError($message = 'Internal Server Error', $status = 500, $extra = []) {
        return response()->json(array_merge([
            'message' => $message,
        ], $extra), $status);
    }
}
