<?php

/**
 * Configuration for the Church Availability & Conflict Detection Engine
 * (App\Services\ChurchAvailabilityService).
 *
 * There is only ONE venue in Sacramenta today (the Parish of the Holy Sacraments), so this
 * engine treats the whole church as a single occupancy timeline per date.
 * Every reservation still carries a location_id (see Reservation::location),
 * so if a parish ever adds a second venue, the engine only needs to key its
 * occupancy timeline by location_id instead of by parish — no change to the
 * core overlap-detection math itself.
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
     * Reservation `type` values that actually occupy the church and must
     * be checked for overlaps. Pamisa sa Kalag is deliberately excluded —
     * it rides on an existing Mass Schedule slot rather than reserving
     * independent church time (see ChurchAvailabilityService and the
     * "Pamisa sa Kalag" workflow in ReservationForm.vue).
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
        // house_blessing, business_blessing, vehicle_blessing,
        // anointing_of_the_sick, spiritual_direction, special_intention,
        // pamisa_sa_kalag, and the generic "others" catch-all are
        // deliberately NOT here — they either happen off-site (the
        // priest travels) or don't reserve independent church time, so
        // they never occupy, and never conflict with, the church itself.
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