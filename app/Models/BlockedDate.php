<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A parish-wide (or, once multiple venues exist, per-venue) blocked period
 * — Christmas, Holy Week, Parish Fiesta, Church Maintenance, Retreat, etc.
 * Consulted by ChurchAvailabilityService before any new reservation is
 * allowed to save; only an override with a recorded reason bypasses it.
 */
class BlockedDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'reason',
        'location_id',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether this blocked period covers the given date, for the given
     * venue (null location_id on the row means "blocks every venue").
     */
    public function coversDate(string $date, ?int $locationId = null): bool
    {
        if ($this->location_id && $locationId && $this->location_id !== $locationId) {
            return false;
        }

        return $this->start_date->toDateString() <= $date && $this->end_date->toDateString() >= $date;
    }
}