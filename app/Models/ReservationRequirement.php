<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single checklist item belonging to a reservation (e.g. "Baptismal
 * Certificate (Groom)" for a wedding). The catalog of which items exist
 * per reservation type, and default scheduling durations, lives in
 * config/reservation_requirements.php — this model is just the per-row
 * record of whether a specific item has been checked off for a specific
 * reservation (see ReservationController::seedRequirements()).
 *
 * `child_index` / `child_name` are only set for Group/Community baptisms,
 * where each child under one reservation gets their own checklist rather
 * than one shared checklist for the whole booking — see the
 * 2026_07_20_000000_add_child_fields_to_reservation_requirements_table
 * migration for why.
 */
class ReservationRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'child_index',
        'child_name',
        'key',
        'label',
        'is_completed',
        'note',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'child_index' => 'integer',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}