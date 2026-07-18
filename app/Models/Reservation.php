<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'contact_name',
        'contact_mobile',
        'contact_email',
        'contact_address',
        'event_date',
        'event_time',
        'priest_id',
        'location_id',
        'status',
        'details',
        'offering_amount',
        'payment_status',
        'amount_paid',
        'receipt_number',
        'payment_date',
        'payment_note',
    ];

    protected $casts = [
        'details' => 'array',
        'event_date' => 'date',
        'payment_date' => 'date',
        'offering_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function priest(): BelongsTo
    {
        return $this->belongsTo(Priest::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ReservationRequirement::class);
    }

    public function rotaAssignments(): HasMany
    {
        return $this->hasMany(RotaAssignment::class);
    }

    /**
     * A reservation is "confirmable" if either it has no checklist items
     * for its type (e.g. house_blessing, others), or every checklist item
     * has been checked off.
     */
    public function requirementsComplete(): bool
    {
        return $this->requirements->isEmpty()
            || $this->requirements->every(fn (ReservationRequirement $r) => $r->is_completed);
    }

    /**
     * Names/labels of any checklist items still outstanding, used to build
     * a specific validation error when someone tries to confirm too early.
     */
    public function incompleteRequirementLabels(): array
    {
        return $this->requirements
            ->where('is_completed', false)
            ->pluck('label')
            ->all();
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