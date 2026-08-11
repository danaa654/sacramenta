<?php

/**
 * Configuration for the Church Availability & Conflict Detection Engine
 * (App\Services\ChurchAvailabilityService).
 *
 * Not every reservation happens in the Main Sanctuary. The engine resolves
 * each reservation to its actual physical venue — Main Sanctuary, a named
 * Chapel, or none at all (off-site/On Campus events) — via
 * ChurchAvailabilityService::resolveVenue(), and only flags a conflict when
 * two occupying events land in the SAME venue with overlapping times:
 *
 *   SAME VENUE + OVERLAPPING TIME      = conflict
 *   DIFFERENT VENUE + OVERLAPPING TIME = no venue conflict
 *
 * `main_sanctuary_types` below and Reservation::location_id (see
 * App\Models\Location) are the two inputs that decide venue; adding a new
 * physical space just means adding a Location row and, if it should be a
 * type's automatic default the way the Main Sanctuary is, teaching
 * resolveVenue() that one additional rule.
 */

return [

    /**
     * Lower number = higher priority. Used only to label/explain a conflict
     * (e.g. "a higher-priority Wedding already holds this slot") — the
     * engine itself is priority-agnostic: ANY two occupying events that
     * overlap are a conflict, regardless of type. Types not listed fall
     * back to the lowest priority (last).
     */
    'priority' => [
        'special_mass' => 1,
        'mass' => 2,
        'wedding' => 3,
        'confirmation' => 4,
        'first_communion' => 5,
        'burial' => 6,
        'baptism' => 7,
        'school_mass' => 8,
        'chapel_mass' => 8,
        'pamisa_sa_kalag' => 9,
    ],

    'default_priority' => 9,

    /**
     * Human-readable labels for the calendar / availability panel.
     */
    'labels' => [
        'mass' => 'Regular Mass',
        'special_mass' => 'Special Mass',
        'wedding' => 'Wedding',
        'baptism' => 'Baptism',
        'burial' => 'Burial',
        'first_communion' => 'First Communion',
        'confirmation' => 'Confirmation',
        'school_mass' => 'School Mass',
        'chapel_mass' => 'Chapel Mass (Fiesta)',
        'pamisa_sa_kalag' => 'Pamisa sa Kalag',
        'house_blessing' => 'House Blessing',
        'business_blessing' => 'Business Blessing',
        'vehicle_blessing' => 'Vehicle Blessing',
        'anointing_of_the_sick' => 'Anointing of the Sick',
        'spiritual_direction' => 'Spiritual Direction',
        'special_intention' => 'Special Intention',
        'others' => 'Other Church Event',
    ],

    /**
     * Reservation `type` values that ALWAYS happen at the parish's single
     * Main Sanctuary — there's no venue picker for these in the form, so
     * StoreReservationRequest auto-assigns the Main Sanctuary Location to
     * them, and ChurchAvailabilityService::resolveVenue() falls back to
     * the Main Sanctuary for them too when no location_id is set yet
     * (e.g. while the admin is still composing the reservation).
     *
     * Pamisa sa Kalag is included here for LOCATION DISPLAY purposes only
     * (it's always physically at the Main Church, so the Reservation
     * Details page shows the same read-only "Main Church" location as
     * everything else in this list) — it is deliberately left OUT of
     * `occupying_types` below, since it attaches to an existing Mass
     * Schedule slot rather than reserving independent church time, and
     * so must never be checked against that slot (or anything else) for
     * an overlap of its own.
     */
    'main_sanctuary_types' => [
        'wedding',
        'baptism',
        'burial',
        'first_communion',
        'confirmation',
        'pamisa_sa_kalag',
    ],

    /**
     * The Location record name treated as "the Main Sanctuary" wherever
     * one isn't explicitly selected. Matches the row seeded by
     * MassScheduleSeeder / ReservationSeeder.
     */
    'main_sanctuary_name' => 'Parish of the Holy Sacraments',

    /**
     * Reservation `type` values that actually occupy a church venue and
     * must be checked for overlaps. NOTE: being in this list means a type
     * is CAPABLE of occupying a venue — whether it actually does, and
     * WHICH venue, is resolved per-reservation by
     * ChurchAvailabilityService::resolveVenue() using location_id and
     * (for School Mass) details.venue. A School Mass held "On Campus"
     * still appears here but resolves to no venue at all, so it never
     * conflicts with anything happening at the church.
     *
     * Pamisa sa Kalag is deliberately excluded — it rides on an existing
     * Mass Schedule slot rather than reserving independent church time
     * (see ChurchAvailabilityService and the "Pamisa sa Kalag" workflow
     * in ReservationForm.vue). House/Business/Vehicle Blessing,
     * Anointing of the Sick, Spiritual Direction, and Special Intention
     * are also excluded — they either happen off-site (the priest
     * travels) or don't reserve independent church time.
     */
    'occupying_types' => [
        'mass',
        'special_mass',
        'wedding',
        'baptism',
        'burial',
        'first_communion',
        'confirmation',
        'school_mass',
        'chapel_mass',
    ],

    /**
     * Window the availability panel displays free/occupied slots within,
     * for a normal (non-blocked) day. 24-hour HH:MM.
     */
    'day_window' => [
        'start' => '05:00',
        'end' => '21:00',
    ],
];