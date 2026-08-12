<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\ReservationDuration;
use Carbon\Carbon;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'series_id',
        'contact_name',
        'contact_mobile',
        'contact_email',
        'contact_address',
        'event_date',
        'event_time',
        'priest_id',
        'location_id',
        'mass_schedule_id',
        'linked_mass_reservation_id',
        'mass_link_needs_review',
        'mass_link_review_reason',
        'status',
        'created_by',
        'updated_by',
        'conflict_overridden',
        'override_reason',
        'overridden_by',
        'overridden_at',
        'archive_reason',
        'details',
        'offering_amount',
        'payment_status',
        'amount_paid',
        'receipt_number',
        'payment_date',
        'payment_note',
    ];

    protected $appends = [
        'reservation_number',
        'display_name',
        'participants',
        'is_locked',
        'venue_category',
        'venue_category_label',
        'marriage_preparation_status',
        'effective_priest',
        'duration_minutes',
        'event_end_time',
    ];

    protected $casts = [
        'details' => 'array',
        'event_date' => 'date',
        'payment_date' => 'date',
        'offering_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'conflict_overridden' => 'boolean',
        'overridden_at' => 'datetime',
        'mass_link_needs_review' => 'boolean',
    ];

    /**
     * Pamisa sa Kalag reservations don't book independent church time —
     * whenever the Mass occurrence they're attached to is cancelled or its
     * date/time changes, flag every linked Pamisa sa Kalag reservation for
     * admin review instead of letting it silently drift out of sync (see
     * "Pamisa sa Kalag <-> Mass Schedule integration").
     */
    protected static function booted(): void
    {
        static::updated(function (Reservation $reservation) {
            if ($reservation->type !== 'mass') {
                return;
            }

            $becameCancelled = $reservation->wasChanged('status') && $reservation->status === 'cancelled';
            $timeChanged = $reservation->wasChanged('event_date') || $reservation->wasChanged('event_time');

            if (! $becameCancelled && ! $timeChanged) {
                return;
            }

            $reason = $becameCancelled
                ? 'The linked Mass was cancelled.'
                : "The linked Mass was rescheduled to {$reservation->event_date->format('M j, Y')}".
                    ($reservation->event_time ? ' '.\Carbon\Carbon::parse($reservation->event_time)->format('g:i A') : '').'.';

            app(\App\Services\PamisaMassLinkService::class)->flagForReview($reservation, $reason);
        });
    }

    public function priest(): BelongsTo
    {
        return $this->belongsTo(Priest::class);
    }

    /**
     * Pamisa sa Kalag has no priest of its own — it's said BY whichever
     * priest is (or later becomes) assigned to the Mass it's linked to
     * (see prepareForValidation() in StoreReservationRequest, which copies
     * priest_id from the Mass at creation time). That copy goes stale the
     * moment an admin assigns/reassigns a priest to the Mass afterward
     * (e.g. via the Mass Schedule "Assign priest" panel), since nothing
     * re-saves every already-linked Pamisa sa Kalag row when that happens.
     *
     * This accessor keeps the two from drifting apart: for a Pamisa sa
     * Kalag reservation it always resolves to the LIVE priest currently
     * on its linked Mass (falling back to its own priest_id, in case it
     * isn't linked to a Mass row for some reason). For every other type
     * it's just the reservation's own priest.
     */
    public function getEffectivePriestAttribute(): ?Priest
    {
        if ($this->type === 'pamisa_sa_kalag') {
            return $this->linkedMass?->priest ?? $this->priest;
        }

        return $this->priest;
    }

    /**
     * The administrator who created this reservation RECORD. Distinct from
     * the EVENT schedule (event_date/event_time) — this is administrative
     * audit metadata only, set automatically on creation, never entered
     * by the administrator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The administrator who last updated this reservation RECORD.
     * Set automatically on every update, never entered by the administrator.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Human-readable reservation record identifier, e.g. "RES-2026-000145".
     * Purely administrative — never used for the EVENT schedule or on
     * certificates, which use event_date instead.
     */
    public function getReservationNumberAttribute(): ?string
    {
        if (! $this->id) {
            return null;
        }

        $year = $this->created_at?->format('Y') ?? now()->format('Y');

        return "RES-{$year}-".str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * How long this reservation occupies the church/priest, in minutes.
     * Delegates to App\Support\ReservationDuration — the same single
     * source of truth ChurchAvailabilityService and
     * SchedulingConflictService use for conflict detection — so the
     * duration shown here (e.g. on the Reservations list) always matches
     * what's actually enforced, including per-type variants (Wedding
     * ceremony_type, group Baptism child count, First Communion batch
     * size, etc.) rather than a flat per-type guess.
     */
    public function getDurationMinutesAttribute(): int
    {
        return ReservationDuration::minutes($this->type, $this->details ?? []);
    }

    /**
     * Computed end time (event_time + duration_minutes), formatted as
     * H:i for the front end to combine with event_time into a "8:00 AM –
     * 9:30 AM" style range on the Reservations list/calendar. Null
     * whenever there's no start time set yet (e.g. a draft still being
     * scheduled), since an end time without a start time is meaningless.
     */
    public function getEventEndTimeAttribute(): ?string
    {
        if (! $this->event_time) {
            return null;
        }

        return Carbon::parse($this->event_time)
            ->addMinutes($this->duration_minutes)
            ->format('H:i:s');
    }

    /**
     * A completed or archived sacramental record is done and read-only by
     * default — no normal Edit action. The only sanctioned way to change
     * one afterward is the audited Correct Record flow (see
     * ReservationController::correct()), never the regular
     * edit()/update() actions, which reject locked reservations outright.
     */
    public function getIsLockedAttribute(): bool
    {
        return in_array($this->status, ['completed', 'archived'], true);
    }

    /**
     * Which of the four venue-usage categories this reservation falls
     * into, driven by the actual selected venue (location_id + its
     * Location::kind) rather than by reservation `type`. A Wedding and a
     * Baptism are pinned to the Main Sanctuary automatically (see
     * StoreReservationRequest::MAIN_SANCTUARY_TYPES), but a School Mass,
     * House Blessing, Business Blessing, or Vehicle Blessing carries
     * whatever venue (or lack of one) the admin actually selected — so
     * two reservations of the same type can land in different categories
     * here, and that's the point: it's the venue that determines whether
     * something blocks the Main Sanctuary, not the sacrament type.
     *
     * Returns one of: 'main_sanctuary', 'chapel', 'other_venue', 'none'.
     * ('chapel' here means an on-site Location marked kind=chapel — NOT
     * the free-text kapilya/barangay chapel used by Chapel Mass, which
     * has no location_id and so falls under 'none'.)
     */
    public function getVenueCategoryAttribute(): string
    {
        $location = $this->location; // uses the eager-loaded relation when present

        if (! $location) {
            return 'none';
        }

        return match ($location->kind ?? 'other') {
            'main_sanctuary' => 'main_sanctuary',
            'chapel' => 'chapel',
            default => 'other_venue',
        };
    }

    /**
     * Human-readable label for venue_category, for the admin Show page and
     * any reservation-list/report views.
     */
    public function getVenueCategoryLabelAttribute(): string
    {
        return match ($this->venue_category) {
            'main_sanctuary' => 'Main Sanctuary',
            'chapel' => 'Chapel',
            'other_venue' => 'Other Venue/Location',
            default => 'No Church Venue Used',
        };
    }

    /**
     * Flattens a (possibly nested) details array into ['dot.path' => leaf
     * value] pairs, skipping array/object nodes themselves and keeping only
     * scalar leaves. Used to diff old vs. new `details` field-by-field for
     * the Correct Record audit trail, so a correction to, say,
     * `children.0.child_name` is recorded as that specific field — not as
     * an opaque "details changed" blob — regardless of the reservation
     * type's particular shape.
     */
    public static function flattenDetails(array $details, string $prefix = ''): array
    {
        $flat = [];

        foreach ($details as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat += self::flattenDetails($value, $path);
            } else {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }

    /**
     * The Reservation Subject / Participant — the person, couple, deceased
     * person, child, family, school, or group the church event is actually
     * FOR, as opposed to contact_name (the Contact Person: whoever made or
     * coordinated the booking, which may or may not be the same person).
     *
     * This is what the Archive list, and certificate generation, should
     * show as the primary identifier — never contact_name on its own.
     * Centralized here so the logic lives in one place instead of being
     * duplicated across the Archive table, reservation Show page, and
     * certificate views.
     */
    /**
     * Short, human-readable label for the reservation's type — e.g. "Mass"
     * for the parish's standing schedule, "Wedding" for a sacrament
     * booking. Used anywhere the UI needs to badge/tag what kind of event
     * this is, separately from who it's for (see notifications bell).
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'mass' => 'Mass',
            'wedding' => 'Wedding',
            'baptism' => 'Baptism',
            'burial' => 'Burial',
            'first_communion' => 'First Communion',
            'confirmation' => 'Confirmation',
            'pamisa_sa_kalag' => 'Pamisa sa Kalag',
            'house_blessing' => 'House Blessing',
            'business_blessing' => 'Business Blessing',
            'chapel_mass' => 'Chapel Mass',
            'school_mass' => 'School Mass',
            'vehicle_blessing' => 'Vehicle Blessing',
            default => ucwords(str_replace('_', ' ', $this->type)),
        };
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->title) {
            return $this->title;
        }

        $d = $this->details ?? [];
        $fallback = $this->contact_name ?: 'N/A';

        return match ($this->type) {
            'wedding' => $this->weddingDisplayName($d, $fallback),
            'baptism' => $this->baptismDisplayName($d, $fallback),
            'burial' => $this->burialDisplayName($d, $fallback),
            'first_communion' => $this->firstCommunionDisplayName($d, $fallback),
            'confirmation' => trim((string) ($d['confirmand_name'] ?? '')) ?: $fallback,
            'pamisa_sa_kalag' => $this->pamisaDisplayName($d, $fallback),
            'house_blessing' => $this->householdDisplayName($fallback),
            'business_blessing' => trim((string) ($d['business_name'] ?? '')) ?: $fallback,
            'chapel_mass' => trim((string) ($d['chapel'] ?? '')) ?: $fallback,
            'school_mass' => trim((string) ($d['school_name'] ?? '')) ?: $fallback,
            'vehicle_blessing' => trim((string) ($d['item_description'] ?? '')) ?: $fallback,
            default => $fallback,
        };
    }

    /**
     * Full list of participant/person names for this reservation — the
     * underlying data the concise Archive label (e.g. "Group Baptism (2)",
     * "Multiple Deceased (2)") is computed FROM, never something stored
     * only as that summarized text. This is what the Archive's "click to
     * view" details panel lists in full, and what makes every individual
     * name in a group reservation searchable (see scopeSearchSubject()).
     *
     * Always returns at least one entry (falling back to contact_name) so
     * the "N person(s)" UI never has to special-case an empty list.
     */
    public function getParticipantsAttribute(): array
    {
        $d = $this->details ?? [];
        $fallback = $this->contact_name ?: 'N/A';

        $names = match ($this->type) {
            'wedding' => array_values(array_filter([
                trim((string) ($d['groom_name'] ?? '')),
                trim((string) ($d['bride_name'] ?? '')),
            ])),
            'baptism' => ($d['baptism_type'] ?? 'individual') === 'group'
                ? $this->namesFrom(collect($d['children'] ?? [])->pluck('child_name')->all())
                : $this->namesFrom($d['child_name'] ?? null),
            'burial' => $this->namesFrom($d['deceased_name'] ?? null),
            'first_communion' => ($d['booking_mode'] ?? 'individual') === 'school_batch'
                ? $this->namesFrom(collect($d['students'] ?? [])->pluck('name')->all())
                : $this->namesFrom($d['child_name'] ?? null),
            'confirmation' => $this->namesFrom($d['confirmand_name'] ?? null),
            'pamisa_sa_kalag' => $this->namesFrom($d['names'] ?? null),
            default => [],
        };

        return empty($names) ? [$fallback] : $names;
    }

    protected function weddingDisplayName(array $d, string $fallback): string
    {
        $groom = trim((string) ($d['groom_name'] ?? ''));
        $bride = trim((string) ($d['bride_name'] ?? ''));

        if ($groom && $bride) {
            return "{$groom} & {$bride}";
        }

        return $groom ?: ($bride ?: $fallback);
    }

    protected function baptismDisplayName(array $d, string $fallback): string
    {
        $isGroup = ($d['baptism_type'] ?? 'individual') === 'group';

        if (! $isGroup) {
            return trim((string) ($d['child_name'] ?? '')) ?: $fallback;
        }

        $count = is_array($d['children'] ?? null) ? count($d['children']) : 0;

        return $count > 0 ? "Group Baptism ({$count})" : 'Group Baptism';
    }

    protected function burialDisplayName(array $d, string $fallback): string
    {
        // Only a single deceased_name field exists in the current data
        // model, but the value is free text — treat comma/"and"-joined
        // names as a hint that this is really a multiple-deceased entry.
        $names = $this->namesFrom($d['deceased_name'] ?? null);

        if (empty($names)) {
            return $fallback;
        }

        return count($names) > 1 ? 'Multiple Deceased ('.count($names).')' : $names[0];
    }

    protected function firstCommunionDisplayName(array $d, string $fallback): string
    {
        $isGroup = ($d['booking_mode'] ?? 'individual') === 'school_batch';

        if ($isGroup) {
            $school = trim((string) ($d['school_name'] ?? ''));

            return $school ? "{$school} — First Communion" : 'First Communion (Group)';
        }

        return trim((string) ($d['child_name'] ?? '')) ?: $fallback;
    }

    protected function pamisaDisplayName(array $d, string $fallback): string
    {
        $names = $this->namesFrom($d['names'] ?? null);

        if (empty($names)) {
            return $fallback;
        }

        return count($names) > 1 ? 'Multiple Deceased ('.count($names).')' : $names[0];
    }

    /**
     * house_blessing has no dedicated family/household-name field in the
     * current data model — contact_name doubles as the household name for
     * this reservation type (per spec: "if no household/family name
     * exists, use the appropriate reservation subject/name").
     */
    protected function householdDisplayName(string $fallback): string
    {
        if ($fallback === 'N/A' || str_contains(strtolower($fallback), 'family')) {
            return $fallback;
        }

        return "{$fallback} Family";
    }

    /**
     * Normalizes a "list of names" field to a flat array of trimmed,
     * non-empty strings. Handles both shapes seen in the data: a plain
     * comma/newline-separated string (what validation expects) and an
     * actual array (what some existing records — e.g. seeded ones —
     * were saved with). Also tolerates a nested array of ['name' => ...]
     * rows, just in case.
     */
    protected function namesFrom(mixed $value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(function ($n) {
                    if (is_array($n)) {
                        $n = $n['name'] ?? '';
                    }

                    return trim((string) $n);
                })
                ->filter()
                ->values()
                ->all();
        }

        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return [];
        }

        return collect(preg_split('/,|\n|\r/', $raw))
            ->map(fn ($n) => trim($n))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The `details` JSON keys that hold subject/participant names for each
     * reservation type — the single source of truth for "what identifies
     * this reservation," reused by both getDisplayNameAttribute() (PHP-side
     * display) and scopeSearchSubject() (DB-side search), so the two never
     * drift apart. Order doesn't matter; a type can list more than one key
     * (e.g. wedding's groom + bride).
     */
    protected const SUBJECT_DETAIL_KEYS = [
        'wedding' => ['groom_name', 'bride_name'],
        'baptism' => ['child_name', 'children'],
        'burial' => ['deceased_name'],
        'first_communion' => ['child_name', 'school_name', 'students', 'parish_or_school_program'],
        'confirmation' => ['confirmand_name'],
        'pamisa_sa_kalag' => ['names'],
        'business_blessing' => ['business_name'],
        'chapel_mass' => ['chapel'],
        'school_mass' => ['school_name'],
        'vehicle_blessing' => ['item_description'],
    ];

    /**
     * Query scope: filters reservations whose Reservation Subject / Display
     * Name (or, secondarily, Contact Person / receipt number) matches every
     * word in $term — case-insensitive, partial match, word order doesn't
     * matter (so "Grace Tan" finds "Sofia Grace Tan").
     *
     * This runs at the database layer (not a PHP-side filter of an
     * already-loaded page), so it works correctly with pagination and scales
     * to a large archive. It searches the *same* fields that
     * getDisplayNameAttribute() draws from (see SUBJECT_DETAIL_KEYS) plus
     * contact_name and receipt_number, so a name visible in the NAME / EVENT
     * column — and on the matching certificate — is always what's searched.
     *
     * JSON fields are matched with a LIKE against their raw JSON_EXTRACT
     * text rather than being decoded row-by-row in PHP. That works whether
     * the underlying value is a plain string (e.g. deceased_name) or an
     * array (e.g. group baptism's `children`, or `names` for Pamisa sa
     * Kalag) — the target substring still appears literally in the
     * extracted JSON text either way — and it's portable across MySQL and
     * SQLite, both of which support JSON_EXTRACT.
     */
    public function scopeSearchSubject($query, ?string $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $words = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $detailKeys = collect(self::SUBJECT_DETAIL_KEYS)->flatten()->unique()->values()->all();

        foreach ($words as $word) {
            $query->where(function ($q) use ($detailKeys, $word) {
                // Primary: the reservation subject, wherever it lives in `details`.
                foreach ($detailKeys as $key) {
                    $q->orWhere("details->{$key}", 'like', "%{$word}%");
                }

                // Secondary: Contact Person, and the O.R. number (kept from
                // the previous search behavior).
                $q->orWhere('contact_name', 'like', "%{$word}%")
                    ->orWhere('receipt_number', 'like', "%{$word}%");
            });
        }

        return $query;
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The recurring weekly template row that auto-generated this
     * reservation, if any. Null for every normal staff-entered booking.
     */
    public function massSchedule(): BelongsTo
    {
        return $this->belongsTo(MassSchedule::class);
    }

    /**
     * The specific Mass occurrence (a type = 'mass' Reservation row) this
     * Pamisa sa Kalag reservation is attached to. Null for every
     * reservation type other than pamisa_sa_kalag.
     */
    public function linkedMass(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'linked_mass_reservation_id');
    }

    /**
     * Inverse of linkedMass() — for a Mass occurrence, every Pamisa sa
     * Kalag reservation attached to it. Powers the printed/announced Mass
     * Intention list (see massIntentionNames()) and the capacity check in
     * ReservationController::massSchedules().
     */
    public function pamisaIntentions(): HasMany
    {
        return $this->hasMany(Reservation::class, 'linked_mass_reservation_id');
    }

    /**
     * Flat, de-duplicated list of deceased names to announce/print for
     * THIS Mass occurrence, drawn from every non-cancelled Pamisa sa Kalag
     * reservation linked to it. Type = 'mass' reservations only; returns
     * an empty array for every other type.
     */
    public function massIntentionNames(): array
    {
        if ($this->type !== 'mass') {
            return [];
        }

        return $this->pamisaIntentions()
            ->where('status', '!=', 'cancelled')
            ->get()
            ->flatMap(fn (Reservation $r) => $r->namesFrom($r->details['names'] ?? null))
            ->unique()
            ->values()
            ->all();
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ReservationRequirement::class);
    }

    /**
     * The Pre-Cana / Marriage Preparation Seminar schedule (wedding
     * reservations only). Separate from `event_date`/`event_time`, which
     * are the wedding ceremony's own schedule — see WeddingSeminar.
     */
    public function seminar(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WeddingSeminar::class);
    }

    public function rotaAssignments(): HasMany
    {
        return $this->hasMany(RotaAssignment::class);
    }

    /**
     * A reservation is "confirmable" if either it has no checklist items
     * for its type (e.g. house_blessing, others), or none of its *required*
     * checklist items are still Pending/In Progress. Optional/supporting
     * items (e.g. a parish-specific certificate) never hold up confirming,
     * regardless of their status — see ReservationRequirement::isBlocking().
     */
    public function requirementsComplete(): bool
    {
        return $this->requirements->isEmpty()
            || $this->requirements->every(fn (ReservationRequirement $r) => ! $r->isBlocking());
    }

    /**
     * Names/labels of any *required* checklist items still outstanding,
     * used to build a specific validation error when someone tries to
     * confirm too early. Optional/supporting items are never listed here,
     * since they don't block confirmation.
     */
    public function incompleteRequirementLabels(): array
    {
        return $this->requirements
            ->filter(fn (ReservationRequirement $r) => $r->isBlocking())
            ->map(fn (ReservationRequirement $r) => $r->child_name ? "{$r->child_name} — {$r->label}" : $r->label)
            ->all();
    }

    /**
     * Overall Marriage Preparation Status for a wedding reservation, shown
     * as a summary banner above the requirements checklist:
     *
     *  - "completed"            — every required Pre-Marriage item is
     *                              Completed (or explicitly Not Required).
     *  - "ready_for_wedding"     — same as above, distinct label once the
     *                              reservation itself has been confirmed.
     *  - "requirements_pending"  — at least one required item is still
     *                              Pending or In Progress.
     *
     * Returns null for any non-wedding reservation, or when the
     * `requirements` relation hasn't been loaded (so accessing this never
     * triggers a surprise query, e.g. from a list page).
     */
    public function getMarriagePreparationStatusAttribute(): ?string
    {
        if ($this->type !== 'wedding' || ! $this->relationLoaded('requirements')) {
            return null;
        }

        $required = $this->requirements->filter(fn (ReservationRequirement $r) => $r->is_required);

        if ($required->isEmpty()) {
            return 'completed';
        }

        $stillPending = $required->contains(fn (ReservationRequirement $r) => $r->isBlocking());

        if ($stillPending) {
            return 'requirements_pending';
        }

        return in_array($this->status, ['confirmed', 'completed'], true) ? 'ready_for_wedding' : 'completed';
    }

    /**
     * Outstanding balance for this reservation's offering/stipend.
     * Never negative; treats a null offering as fully settled (nothing owed).
     */
    public function balanceDue(): float
    {
        $offering = (float) ($this->offering_amount ?? 0);
        $paid = (float) ($this->amount_paid ?? 0);

        return max(0, $offering - $paid);
    }
}