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

        // Wedding Requirements is split into two groups:
        //
        //  - "Pre-Marriage Requirements" (`is_required` => true): the core
        //    pastoral checklist. An item here left Pending or In Progress
        //    is what keeps a wedding from being confirmed — see
        //    Reservation::requirementsComplete().
        //
        //  - "Documents Requirements" (`is_required` => false): the
        //    bride/groom document checklist (baptismal certificate,
        //    CENOMAR, civil marriage license — one row per side so each
        //    side's copy can be tracked/verified independently). Tracked
        //    with a Pending / Submitted / Verified / Not Required status,
        //    but — same as Supporting Documents below — never blocks
        //    confirming the reservation.
        //
        //    NOTE: a "Confirmation Certificate" item previously lived
        //    here. It was deliberately removed (see the
        //    2026_08_11_000003 migration) and must not be reintroduced —
        //    it is not part of the parish's wedding document checklist.
        //
        //  - "Parish-Specific / Supporting Documents" (`is_required` =>
        //    false): anything else that varies by parish (currently just
        //    the reservation deposit).
        'wedding' => [
            [
                'key' => 'canonical_interview',
                'label' => 'Canonical Interview Completed',
                'is_required' => true,
                'group_key' => 'pre_marriage',
                'group_label' => 'Pre-Marriage Requirements',
            ],
            [
                'key' => 'marriage_banns',
                'label' => 'Marriage Banns Posted',
                'description' => 'Usually announced for 3 consecutive weeks, according to parish requirements.',
                'is_required' => true,
                'group_key' => 'pre_marriage',
                'group_label' => 'Pre-Marriage Requirements',
            ],
            [
                'key' => 'pre_cana_seminar',
                'label' => 'Pre-Cana / Marriage Preparation Seminar Completed',
                'is_required' => true,
                'group_key' => 'pre_marriage',
                'group_label' => 'Pre-Marriage Requirements',
            ],
            [
                'key' => 'required_documents_verified',
                'label' => 'Required Documents Verified',
                'is_required' => true,
                'group_key' => 'pre_marriage',
                'group_label' => 'Pre-Marriage Requirements',
            ],
            [
                'key' => 'reservation_deposit',
                'label' => 'Reservation Deposit Paid',
                'is_required' => false,
                'group_key' => 'supporting',
                'group_label' => 'Parish-Specific / Supporting Documents',
            ],
            [
                'key' => 'baptismal_certificate_groom',
                'label' => 'Baptismal Certificate, For Marriage Purposes (Groom)',
                'is_required' => false,
                'group_key' => 'documents',
                'group_label' => 'Documents Requirements',
            ],
            [
                'key' => 'baptismal_certificate_bride',
                'label' => 'Baptismal Certificate, For Marriage Purposes (Bride)',
                'is_required' => false,
                'group_key' => 'documents',
                'group_label' => 'Documents Requirements',
            ],
            [
                'key' => 'cenomar_groom',
                'label' => 'Cenomar / Certificate of No Marriage (Groom)',
                'is_required' => false,
                'group_key' => 'documents',
                'group_label' => 'Documents Requirements',
            ],
            [
                'key' => 'cenomar_bride',
                'label' => 'Cenomar / Certificate of No Marriage (Bride)',
                'is_required' => false,
                'group_key' => 'documents',
                'group_label' => 'Documents Requirements',
            ],
            [
                'key' => 'civil_marriage_license_groom',
                'label' => 'Marriage License (Groom)',
                'is_required' => false,
                'group_key' => 'documents',
                'group_label' => 'Documents Requirements',
            ],
            [
                'key' => 'civil_marriage_license_bride',
                'label' => 'Marriage License (Bride)',
                'is_required' => false,
                'group_key' => 'documents',
                'group_label' => 'Documents Requirements',
            ],
            [
                'key' => 'other_document_bride',
                'label' => 'Other Required Document(s) — Bride',
                'is_required' => false,
                'group_key' => 'documents',
                'group_label' => 'Documents Requirements',
            ],
            [
                'key' => 'other_document_groom',
                'label' => 'Other Required Document(s) — Groom',
                'is_required' => false,
                'group_key' => 'documents',
                'group_label' => 'Documents Requirements',
            ],
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
        // Regular auto-generated parish Mass slot (see MassSchedule /
        // GenerateMassSchedule) — matches the 1-hour back-to-back slots
        // in all three weekly templates.
        'mass' => 60,
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

    /**
     * Wedding duration depends on which ceremony the couple chose (see
     * ReservationForm.vue `details.ceremony_type`). A Liturgy of the Word
     * (no Mass, no Communion) runs noticeably shorter than a full Nuptial
     * Mass. `durations.wedding` above (90) remains the fallback used only
     * when `ceremony_type` is missing (e.g. legacy records).
     */
    'durations_wedding' => [
        'nuptial_mass' => 90,
        'liturgy_of_the_word' => 45,
    ],

    /**
     * Baptism duration depends on `details.baptism_type`. A Group/Community
     * Baptism takes longer the more children are being baptized — base
     * time for the shared parts of the rite, plus a per-child increment for
     * the individual anointing/naming portion, capped at `max` so an
     * unusually large group still books a bounded, sane block of church
     * time instead of ballooning indefinitely.
     */
    'durations_baptism' => [
        'individual' => 30,
        'group' => [
            'base' => 45,
            'per_child' => 5,
            'max' => 150,
        ],
    ],

    /**
     * First Communion duration depends on `details.booking_mode`. A
     * School/Group batch takes longer with more students (extra time to
     * process each child through the rite), capped at `max` for the same
     * reason as the Baptism group cap above.
     */
    'durations_first_communion' => [
        'individual' => 45,
        'school_batch' => [
            'base' => 60,
            'per_student' => 2,
            'max' => 180,
        ],
    ],

];