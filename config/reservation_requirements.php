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
            ['key' => 'reservation_deposit', 'label' => 'Reservation Deposit Paid'],
            ['key' => 'baptismal_certificate_groom', 'label' => "Baptismal Certificate, For Marriage Purposes (Groom)"],
            ['key' => 'baptismal_certificate_bride', 'label' => "Baptismal Certificate, For Marriage Purposes (Bride)"],
            ['key' => 'confirmation_certificate_groom', 'label' => "Confirmation Certificate, For Marriage Purposes (Groom)"],
            ['key' => 'confirmation_certificate_bride', 'label' => "Confirmation Certificate, For Marriage Purposes (Bride)"],
            ['key' => 'cenomar_groom', 'label' => 'Cenomar / Certificate of No Marriage (Groom)'],
            ['key' => 'cenomar_bride', 'label' => 'Cenomar / Certificate of No Marriage (Bride)'],
            ['key' => 'civil_marriage_license', 'label' => 'Civil Marriage License'],
            ['key' => 'canonical_interview', 'label' => 'Canonical Interview Completed'],
            ['key' => 'marriage_banns', 'label' => 'Marriage Banns Posted (3 consecutive weeks, both parishes)'],
            ['key' => 'pre_cana_seminar', 'label' => 'Pre-Cana Seminar Completed'],
        ],

        'baptism' => [
            ['key' => 'birth_certificate', 'label' => "Child's Birth Certificate on File"],
            ['key' => 'parents_marriage_certificate', 'label' => "Parents' Marriage Certificate (or noted N/A)"],
            ['key' => 'godparent_eligibility', 'label' => 'Godparent Eligibility Confirmed (practicing Catholic, 16+, confirmed)'],
            ['key' => 'pre_baptism_seminar', 'label' => 'Pre-Baptism Seminar Attended'],
        ],

        'burial' => [
            ['key' => 'death_certificate', 'label' => 'Death Certificate on File'],
        ],

        'first_communion' => [
            ['key' => 'baptismal_certificate', 'label' => 'Baptismal Certificate on File'],
            ['key' => 'catechism_completion', 'label' => 'Catechism / CCD Class Completion Confirmed'],
        ],

        'confirmation' => [
            ['key' => 'baptismal_certificate', 'label' => 'Baptismal Certificate on File'],
            ['key' => 'sponsor_eligibility', 'label' => 'Sponsor Eligibility Confirmed (practicing Catholic, confirmed)'],
            ['key' => 'confirmation_class_completion', 'label' => 'Confirmation Class Completion Confirmed'],
        ],

        // pamisa_sa_kalag, school_mass, chapel_mass, house_blessing,
        // business_blessing, vehicle_blessing, anointing_of_the_sick,
        // spiritual_direction, special_intention, others: no checklist by
        // default — these are lighter-weight bookings (a stipend, a date,
        // or a simple logistics ask) rather than document-gated sacraments.
        // Add entries here if a parish needs one.
    ],

    /**
     * Default reservation slot duration, in minutes, used for scheduling
     * conflict detection. Override per type as needed (e.g. weddings often
     * need a longer block than the default 30-minute slot).
     *
     * Burial uses the longer Full Funeral Mass duration (up to 90 minutes
     * for a well-attended Mass) as a conservative estimate, since a
     * shorter Funeral Service (no Mass, ~20-30 min) booked at the true
     * shorter length could risk double-booking the priest.
     *
     * Baptism similarly uses the longer Group/Community Baptism duration
     * (~60 min) as a conservative estimate over a shorter Individual/
     * Private Baptism (~20-30 min).
     *
     * Wedding uses the longer Nuptial Mass duration (up to 1.5 hours) as
     * a conservative estimate over a shorter Liturgy of the Word only
     * ceremony (~30-45 min).
     *
     * Pamisa sa Kalag uses the longer Sunday Mass duration (~60 min) as
     * a conservative estimate over a shorter daily Mass (~30-45 min) —
     * the intention is read during whichever Mass the family picks.
     *
     * House Blessing uses the blessing ceremony itself (~30 min, the
     * upper end of the 15-30 min range) for conflict-checking purposes.
     * The optional reception/meal afterward is unscheduled, variable-
     * length family time and isn't reserved as part of the priest's slot.
     *
     * School Mass uses the upper end of its 1 to 1.5 hour range, since
     * communion for a large student body (and any closing performances
     * or speeches) can run a standard Mass longer than usual.
     *
     * First Communion and Confirmation are typically celebrated as group
     * Masses, so they use a similar conservative 60-90 minute estimate.
     * Confirmation is set slightly longer since a bishop or presiding
     * priest confirming many candidates individually tends to run long.
     *
     * The "Others" sub-categories are sized to their real-world length:
     * a Vehicle/Article Blessing is quick (~10 min at the courtyard),
     * Anointing of the Sick is a short bedside rite (~20 min), and
     * Spiritual Direction/Confession and a Business Blessing run about
     * as long as a House Blessing (~30 min each).
     */
    'durations' => [
        'default' => 30,
        'burial' => 90,
        'baptism' => 60,
        'wedding' => 90,
        'pamisa_sa_kalag' => 60,
        'house_blessing' => 30,
        'school_mass' => 90,
        'first_communion' => 60,
        'confirmation' => 90,
        'business_blessing' => 30,
        'vehicle_blessing' => 10,
        'anointing_of_the_sick' => 20,
        'spiritual_direction' => 30,
        'special_intention' => 30,
    ],

];