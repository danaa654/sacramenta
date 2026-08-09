<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'name',
        'kind',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The set of recognized venue kinds. Used for validation (see the
     * Locations admin controller/request, if/when one is added) and for
     * driving the admin-facing label below. Anything not in this list is
     * treated as 'other'.
     */
    public const KINDS = ['main_sanctuary', 'chapel', 'other'];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isMainSanctuary(): bool
    {
        return $this->kind === 'main_sanctuary';
    }

    public function isChapel(): bool
    {
        return $this->kind === 'chapel';
    }

    /**
     * Human-readable venue category, e.g. for badges next to a reservation's
     * selected venue in the admin UI. Mirrors Reservation::getVenueCategoryLabelAttribute()
     * for a reservation that HAS a venue; that accessor additionally covers
     * the "no church venue used" case for reservations with no location_id.
     */
    public function getKindLabelAttribute(): string
    {
        return match ($this->kind) {
            'main_sanctuary' => 'Main Sanctuary',
            'chapel' => 'Chapel',
            default => 'Other Venue',
        };
    }
}