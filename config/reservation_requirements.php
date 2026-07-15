<?php

/**
 * Per-type requirement checklists and scheduling durations.
 *
 * This mirrors the pattern already used for conditional `details` fields in
 * StoreReservationRequest::conditionalRules() — one array, keyed by
 * reservation `type`, describing what's needed. Types not listed here (or
 * listed with an empty array) simply have no checklist, and are treated as
 * immediately confirmable.
 */

return [

    'checklists' => [

        'wedding' => [
            ['key' => 'baptismal_certificate_groom', 'label' => "Baptismal Certificate (Groom)"],
            ['key' => 'baptismal_certificate_bride', 'label' => "Baptismal Certificate (Bride)"],
            ['key' => 'confirmation_certificate_groom', 'label' => "Confirmation Certificate (Groom)"],
            ['key' => 'confirmation_certificate_bride', 'label' => "Confirmation Certificate (Bride)"],
            ['key' => 'canonical_interview', 'label' => 'Canonical Interview Completed'],
            ['key' => 'marriage_banns', 'label' => 'Marriage Banns Posted'],
            ['key' => 'pre_cana_seminar', 'label' => 'Pre-Cana Seminar Completed'],
        ],

        'baptism' => [
            ['key' => 'parents_marriage_certificate', 'label' => "Parents' Marriage Certificate (or noted N/A)"],
            ['key' => 'godparent_eligibility', 'label' => 'Godparent Eligibility Confirmed'],
        ],

        'burial' => [
            ['key' => 'death_certificate', 'label' => 'Death Certificate on File'],
        ],

        // pamisa_sa_kalag, school_mass, chapel_mass, house_blessing, others:
        // no checklist by default. Add entries here if a parish needs one.
    ],

    /**
     * Default reservation slot duration, in minutes, used for scheduling
     * conflict detection. Override per type as needed (e.g. weddings often
     * need a longer block than the default 30-minute slot).
     */
    'durations' => [
        'default' => 30,
        // 'wedding' => 60,
    ],

];