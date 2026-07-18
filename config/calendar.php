<?php

/**
 * Calendar display rules shared by the admin FullCalendar view and the
 * public parishioner calendar.
 *
 * `colors` maps each reservation type to the standard color language used
 * across both calendars (hex values are handed straight to FullCalendar's
 * `backgroundColor`/`borderColor` event props).
 *
 * `public_types` lists the reservation types a non-staff visitor is allowed
 * to see on the homepage calendar at all (Mass schedules, community
 * baptism slots, confession hours). Everything else (weddings, burials,
 * house blessings, special intentions, etc.) is parish-business and never
 * appears publicly, regardless of status. Public events only ever show
 * confirmed reservations, and only the date/time/type — never the
 * contact name, phone, fees, or notes; see ReservationController
 * public visibility handling.
 */

return [

    'colors' => [
        'wedding' => '#d97706', // orange
        'baptism' => '#2563eb', // blue
        'burial' => '#4b5563', // charcoal/grey
        'chapel_mass' => '#16a34a', // green
        'school_mass' => '#16a34a', // green
        'first_communion' => '#16a34a', // green (group Mass)
        'confirmation' => '#16a34a', // green (group Mass)
        'pamisa_sa_kalag' => '#16a34a', // green (said during a regular Mass)
        'house_blessing' => '#7c3aed', // purple
        'business_blessing' => '#7c3aed', // purple
        'vehicle_blessing' => '#7c3aed', // purple
        'anointing_of_the_sick' => '#7c3aed', // purple
        'spiritual_direction' => '#7c3aed', // purple
        'special_intention' => '#7c3aed', // purple
        'others' => '#7c3aed', // purple
    ],

    'default_color' => '#7c3aed',

    'public_types' => [
        'chapel_mass',
        'school_mass',
        'baptism',
        'spiritual_direction',
    ],

];