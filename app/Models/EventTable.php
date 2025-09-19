<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTable extends Model
{
    protected $fillable = [
        'name',
        'nro_asientos',
        'position_x',
        'position_y',
    ];

    protected $casts = [
        'nro_asientos' => 'integer',
        'position_x' => 'float',
        'position_y' => 'float',
    ];

    /**
     * Get the guests assigned to this table.
     */
    public function guests()
    {
        return $this->hasMany(Guest::class, 'event_table_id');
    }

    /**
     * Get the count of assigned guests (including companions)
     */
    public function getAssignedCountAttribute()
    {
        return $this->guests()->count() + 
               $this->guests()->where('enable_companion', true)->count();
    }

    /**
     * Get available seats
     */
    public function getAvailableSeatsAttribute()
    {
        return $this->nro_asientos - $this->assigned_count;
    }

    /**
     * Check if table is full
     */
    public function getIsFullAttribute()
    {
        return $this->assigned_count >= $this->nro_asientos;
    }
}
