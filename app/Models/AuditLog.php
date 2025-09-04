<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\GeneratesUuid;

class AuditLog extends Model
{
    use GeneratesUuid;

    protected $table = 'audit_logs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id','guest_id','action','field','old_value','new_value','source'
    ];

    protected static function boot()
    {
        parent::boot();
        static::bootGeneratesUuid();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}
