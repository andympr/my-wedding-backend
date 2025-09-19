<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Traits\GeneratesUuid;

class Guest extends Model
{
    use GeneratesUuid;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'lastname',
        'email',
        'phone',
        'enable_companion',
        'confirm',        // 'pending' | 'yes' | 'no'
        'token',
        'notes',
        'message',
        'event_table_id',
        'invitation_sent',
        'confirmed_at',
        'declined_at',
    ];

    protected $casts = [
        'enable_companion' => 'boolean',
        'invitation_sent'  => 'boolean',
        'confirmed_at'     => 'datetime',
        'declined_at'      => 'datetime',
    ];

    // Además del UUID, generamos token si no existe
    protected static function booted()
    {
        static::creating(function ($guest) {
            if (! $guest->token) {
                $guest->token = (string) Str::uuid();
            }
        });
    }

    // 0 o 1 acompañante
    public function companion()
    {
        return $this->hasOne(Companion::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    // Relación con tabla
    public function eventTable()
    {
        return $this->belongsTo(EventTable::class, 'event_table_id');
    }
}