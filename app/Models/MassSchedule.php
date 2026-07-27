<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring weekly Mass template row (SUNDAY, WEEKDAY, or FRIDAY schedule).
 * See the create_mass_schedules_table migration for the full rationale.
 */
class MassSchedule extends Model
{
    protected $fillable = [
        'label',
        'days_of_week',
        'start_time',
        'end_time',
        'language',
        'is_livestreamed',
        'location_id',
        'is_active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'is_livestreamed' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Reservations that were auto-generated from this template row.
     * Editing/cancelling one of these never touches the template itself.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Whether this template applies on the given Carbon-style weekday
     * integer (0 = Sunday ... 6 = Saturday).
     */
    public function appliesOnDayOfWeek(int $dayOfWeek): bool
    {
        return in_array($dayOfWeek, $this->days_of_week ?? [], strict: true);
    }
}