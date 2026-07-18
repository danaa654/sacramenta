<?php

/**
 * Default ministry/volunteer roles requested when a reservation of a given
 * type is CONFIRMED (mirrors the config/reservation_requirements.php
 * pattern — one array keyed by reservation `type`, seeded onto the
 * reservation's rota_assignments rows at confirmation time).
 *
 * `count` produces that many numbered slots for the role (e.g. count: 2 on
 * altar_server creates "Altar Server #1" and "Altar Server #2" as separate
 * assignable rows). Priest is intentionally left out here — it's already
 * tracked via reservations.priest_id and shown separately in the UI.
 *
 * Types not listed (or listed with an empty array) get no rota rows,
 * matching how reservation_requirements treats unlisted types.
 */

return [

    'wedding' => [
        ['key' => 'altar_server', 'label' => 'Altar Server', 'count' => 2],
        ['key' => 'commentator_lector', 'label' => 'Commentator / Lector', 'count' => 1],
        ['key' => 'choir', 'label' => 'Choir Group', 'count' => 1],
        ['key' => 'usher', 'label' => 'Usher', 'count' => 2],
    ],

    'baptism' => [
        ['key' => 'altar_server', 'label' => 'Altar Server', 'count' => 1],
    ],

    'burial' => [
        ['key' => 'altar_server', 'label' => 'Altar Server', 'count' => 1],
        ['key' => 'commentator_lector', 'label' => 'Commentator / Lector', 'count' => 1],
    ],

    'first_communion' => [
        ['key' => 'altar_server', 'label' => 'Altar Server', 'count' => 2],
        ['key' => 'commentator_lector', 'label' => 'Commentator / Lector', 'count' => 1],
        ['key' => 'choir', 'label' => 'Choir Group', 'count' => 1],
        ['key' => 'usher', 'label' => 'Usher', 'count' => 2],
    ],

    'confirmation' => [
        ['key' => 'altar_server', 'label' => 'Altar Server', 'count' => 2],
        ['key' => 'commentator_lector', 'label' => 'Commentator / Lector', 'count' => 1],
        ['key' => 'choir', 'label' => 'Choir Group', 'count' => 1],
        ['key' => 'usher', 'label' => 'Usher', 'count' => 2],
    ],

    'school_mass' => [
        ['key' => 'altar_server', 'label' => 'Altar Server', 'count' => 2],
        ['key' => 'commentator_lector', 'label' => 'Commentator / Lector', 'count' => 1],
        ['key' => 'usher', 'label' => 'Usher', 'count' => 2],
    ],

    'chapel_mass' => [
        ['key' => 'commentator_lector', 'label' => 'Commentator / Lector', 'count' => 1],
    ],

    // pamisa_sa_kalag, house_blessing, business_blessing, vehicle_blessing,
    // anointing_of_the_sick, spiritual_direction, special_intention,
    // others: no rota by default — no separate ministry team beyond the
    // priest is normally needed. Add entries here if a parish needs one.
];