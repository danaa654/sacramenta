<?php

/**
 * Configurable rules used by MarriagePreparationSchedulingService to
 * SUGGEST dates/times/venues for the four marriage-preparation activities
 * once a Wedding Date is set. These are only ever starting suggestions —
 * the admin can freely edit every generated value (see
 * ReservationRequirement.schedule_source / WeddingSeminar.schedule_source,
 * which flip to "manual" the moment an admin edits them and are then left
 * alone by future auto-generation).
 *
 * Different parishes run marriage preparation on different timelines, so
 * every offset lives here rather than being hard-coded in the frontend or
 * the service class — a future "Parish Settings" screen can read/write
 * these same keys without touching any scheduling logic.
 *
 * `offset_days` / `start_offset_days` / `end_offset_days` all count
 * backwards from the Wedding Date. E.g. a Wedding Date of Sept 25 with
 * `offset_days => 20` suggests Sept 5.
 */

return [

    'canonical_interview' => [
        'offset_days' => 20,
        'default_time' => '10:00',
        'duration_minutes' => 60,
        'default_venue' => 'Parish Office',
    ],

    'pre_cana_seminar' => [
        'offset_days' => 13,
        'default_start_time' => '08:00',
        'default_end_time' => '12:00',
        'default_venue' => 'Parish Hall',
    ],

    'marriage_banns' => [
        // Announced on 3 consecutive weeks (7 days apart), the last one
        // finishing about 10 days before the wedding — per typical parish
        // practice. `third_offset_days` counts backwards from the Wedding
        // Date; the 1st/2nd announcements are `interval_days` further back
        // from there.
        'third_offset_days' => 10,
        'interval_days' => 7,
        'default_venue' => 'Parish of the Holy Sacraments',
    ],

    'wedding_rehearsal' => [
        // Kept for backward compatibility (e.g. validateBeforeWedding
        // callers that just want "a" suggested date) — the actual
        // suggestion search below tries 'offset_days_priority' first.
        'offset_days' => 2,
        'default_time' => '17:00',
        'duration_minutes' => 60,
        'default_venue' => null, // defaults to the wedding's own venue (Main Church)

        // Automatic availability search order — see requirement #4/#5.
        // Days-before-wedding are tried in this priority order (2 days
        // before is preferred; 1 and 3 are the fallback window), and for
        // each day every time in `time_candidates` is tried in order
        // before moving to the next day.
        'offset_days_priority' => [2, 1, 3],
        'time_candidates' => ['17:00', '18:00', '19:00', '16:00'],
    ],

];