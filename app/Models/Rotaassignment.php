<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RotaAssignment extends Model
{
    protected $fillable = [
        'reservation_id',
        'role_key',
        'role_label',
        'slot_number',
        'volunteer_name',
        'status',
        'note',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}