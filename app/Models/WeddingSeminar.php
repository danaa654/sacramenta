<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The Pre-Cana / Marriage Preparation Seminar schedule for a wedding
 * reservation. Deliberately its own model/table rather than more columns
 * on ReservationRequirement — a seminar has its own date, time range,
 * venue, and one-or-more facilitators, none of which fit the flat
 * "single status + note" shape the rest of the checklist uses.
 *
 * The `pre_cana_seminar` ReservationRequirement row still exists and is
 * what actually blocks/unblocks confirming the wedding (it stays
 * Pending/Scheduled/Completed in step with this record — see
 * ReservationController::syncSeminarRequirementStatus()). This model is
 * the schedule; that row is the checklist status.
 */
class WeddingSeminar extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NOT_REQUIRED = 'not_required';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SCHEDULED,
        self::STATUS_COMPLETED,
        self::STATUS_NOT_REQUIRED,
    ];

    /** Suggested venues shown in the picker; "Other" reveals a free-text field (`venue_other`). */
    public const VENUE_OPTIONS = [
        'Parish Hall',
        'Parish Meeting Room',
        'Formation Room',
        'Conference Room',
        'Pastoral Center',
        'Other',
    ];

    public const FACILITATOR_TYPES = [
        'priest' => 'Priest',
        'lay_facilitator' => 'Lay Facilitator',
        'couple_facilitator' => 'Married Couple / Couple Facilitator',
        'other' => 'Other',
    ];

    protected $fillable = [
        'reservation_id',
        'seminar_date',
        'start_time',
        'end_time',
        'venue',
        'venue_other',
        'facilitators',
        'notes',
        'status',
        'completed_at',
        // 'generated' when MarriagePreparationSchedulingService produced
        // this schedule from the Wedding Date, 'manual' the moment an
        // admin schedules/edits it themselves (see SeminarController).
        // Null for older rows that predate this feature.
        'schedule_source',
    ];

    protected $casts = [
        'seminar_date' => 'date',
        'facilitators' => 'array',
        'completed_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** The venue actually displayed — the custom text when "Other" was picked. */
    public function getDisplayVenueAttribute(): ?string
    {
        return $this->venue === 'Other' ? $this->venue_other : $this->venue;
    }

    /** Comma-joined facilitator names, for compact display (calendar chips, list rows). */
    public function getFacilitatorNamesAttribute(): string
    {
        return collect($this->facilitators ?? [])
            ->pluck('name')
            ->filter()
            ->implode(', ');
    }
}