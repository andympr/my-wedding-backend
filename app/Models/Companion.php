<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Companion extends Model
{
    protected $table = 'companions';

    protected $fillable = ['guest_id','name','lastname'];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}
