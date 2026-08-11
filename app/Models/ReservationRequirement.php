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

    /**
     * Valid values for `status`. Replaces the old plain boolean model,
     * where a requirement could only be "done" or "not done" — a parish
     * can now also mark something as currently underway, or explicitly
     * not applicable to them, instead of it just sitting unchecked.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NOT_REQUIRED = 'not_required';

    /**
     * `submitted` only applies to `group_key = 'documents'` items (the
     * Pending -> Submitted -> Verified flow for a document a couple
     * hands in); `in_progress` only applies to Pre-Marriage Requirements
     * items like Marriage Banns. Both are accepted here rather than
     * having two separate columns — the UI only offers the options that
     * make sense for the item's group (see WeddingRequirementsPanel.vue),
     * this list is just the full set the column accepts.
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_SUBMITTED,
        self::STATUS_COMPLETED,
        self::STATUS_NOT_REQUIRED,
    ];

    protected $fillable = [
        'reservation_id',
        'child_index',
        'child_name',
        'key',
        'label',
        'description',
        'is_completed',
        'status',
        'is_required',
        'group_key',
        'group_label',
        'note',
        'date_started',
        'date_completed',
        'meta',
        // 'generated' when MarriagePreparationSchedulingService produced
        // this item's date(s) from the Wedding Date, 'manual' the moment
        // an admin edits it themselves. Only meaningful for wedding
        // marriage-preparation items (canonical_interview, marriage_banns,
        // wedding_rehearsal) — null for every other checklist item.
        'schedule_source',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_required' => 'boolean',
        'child_index' => 'integer',
        'date_started' => 'date',
        'date_completed' => 'date',
        'meta' => 'array',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    protected static function booted(): void
    {
        // Keep the legacy `is_completed` boolean in sync with the new
        // `status` column whenever status changes, so anything that still
        // reads is_completed directly (reports, older queries, etc.)
        // continues to work without needing to know about statuses.
        static::saving(function (self $requirement) {
            if ($requirement->isDirty('status')) {
                $requirement->is_completed = $requirement->status === self::STATUS_COMPLETED;
            }
        });
    }

    /**
     * Whether this item, in its current status, is holding up confirming
     * the reservation. Optional/supporting items (`is_required` false)
     * never block regardless of status; required items only block while
     * they're Pending or In Progress.
     */
    public function isBlocking(): bool
    {
        if (! $this->is_required) {
            return false;
        }

        return ! in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_NOT_REQUIRED], true);
    }
}