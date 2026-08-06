<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Priest;
use App\Models\Reservation;
use Illuminate\Database\Seeder;

/**
 * Sample past records for the Archives page: a handful of completed /
 * archived reservations, weighted toward the four types that produce a
 * printable certificate (baptism, wedding, burial, first_communion) so
 * "Print Certificate" has something real to render, plus a couple of
 * non-certificate types so the "no certificate for this type" gating is
 * also visible in the list. Dev/staging convenience only — safe to skip
 * or remove once real reservations exist.
 */
class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        $priest = Priest::first() ?? Priest::create(['name' => 'Fr. Miguel Santos', 'status' => 'active']);
        $priest2 = Priest::skip(1)->first() ?? $priest;
        $mainSanctuary = Location::where('name', 'Main Sanctuary')->first();
        $parishHall = Location::where('name', 'Parish Hall')->first();

        // Baptism — individual
        Reservation::firstOrCreate(
            ['contact_name' => 'Ramon Villareal', 'type' => 'baptism', 'event_date' => '2026-06-14'],
            [
                'contact_mobile' => '0917-555-0142',
                'contact_email' => 'ramon.villareal@example.com',
                'contact_address' => 'Cebu City',
                'event_time' => '10:00:00',
                'priest_id' => $priest->id,
                'location_id' => $mainSanctuary?->id,
                'status' => 'completed',
                'details' => [
                    'baptism_type' => 'individual',
                    'child_name' => 'Isabella Marie Villareal',
                    'father_name' => 'Ramon Villareal',
                    'mother_maiden_name' => 'Carmela Dizon',
                    'godparents' => [
                        ['name' => 'Antonio Reyes'],
                        ['name' => 'Luisa Fernandez'],
                    ],
                ],
                'offering_amount' => 500,
                'payment_status' => 'paid',
                'amount_paid' => 500,
                'receipt_number' => 'OR-2026-0142',
                'payment_date' => '2026-06-10',
            ]
        );

        // Baptism — group, archived
        Reservation::firstOrCreate(
            ['contact_name' => 'Parish Office (Group Baptism)', 'type' => 'baptism', 'event_date' => '2026-05-02'],
            [
                'contact_mobile' => '0917-555-0100',
                'contact_address' => 'Cebu City',
                'event_time' => '09:00:00',
                'priest_id' => $priest->id,
                'location_id' => $mainSanctuary?->id,
                'status' => 'archived',
                'archive_reason' => 'completed',
                'details' => [
                    'baptism_type' => 'group',
                    'children' => [
                        [
                            'child_name' => 'Josef Andrei Lim',
                            'father_name' => 'Andres Lim',
                            'mother_maiden_name' => 'Teresa Uy',
                            'godparents' => [['name' => 'Marco Santos']],
                        ],
                        [
                            'child_name' => 'Grace Anne Bautista',
                            'father_name' => 'Ferdinand Bautista',
                            'mother_maiden_name' => 'Nora Aquino',
                            'godparents' => [['name' => 'Elena Cruz']],
                        ],
                    ],
                ],
                'offering_amount' => 800,
                'payment_status' => 'paid',
                'amount_paid' => 800,
                'receipt_number' => 'OR-2026-0100',
                'payment_date' => '2026-04-28',
            ]
        );

        // Wedding
        Reservation::firstOrCreate(
            ['contact_name' => 'Diego Fernandez', 'type' => 'wedding', 'event_date' => '2026-04-18'],
            [
                'contact_mobile' => '0918-555-0177',
                'contact_email' => 'diego.fernandez@example.com',
                'contact_address' => 'Mandaue City',
                'event_time' => '14:00:00',
                'priest_id' => $priest2->id,
                'location_id' => $mainSanctuary?->id,
                'status' => 'completed',
                'details' => [
                    'groom_name' => 'Diego Fernandez',
                    'bride_name' => 'Anna Liza Morales',
                    'ceremony_type' => 'nuptial_mass',
                ],
                'offering_amount' => 5000,
                'payment_status' => 'paid',
                'amount_paid' => 5000,
                'receipt_number' => 'OR-2026-0177',
                'payment_date' => '2026-04-01',
            ]
        );

        // Burial
        Reservation::firstOrCreate(
            ['contact_name' => 'Corazon Santos', 'type' => 'burial', 'event_date' => '2026-03-22'],
            [
                'contact_mobile' => '0919-555-0188',
                'contact_address' => 'Cebu City',
                'event_time' => '08:00:00',
                'priest_id' => $priest->id,
                'location_id' => $mainSanctuary?->id,
                'status' => 'completed',
                'details' => [
                    'deceased_name' => 'Eduardo Santos',
                    'age' => 74,
                    'service_type' => 'funeral_mass',
                    'cemetery' => 'Cebu Memorial Park',
                ],
                'offering_amount' => 1500,
                'payment_status' => 'paid',
                'amount_paid' => 1500,
                'receipt_number' => 'OR-2026-0188',
                'payment_date' => '2026-03-20',
            ]
        );

        // First Communion — individual
        Reservation::firstOrCreate(
            ['contact_name' => 'Michael Tan', 'type' => 'first_communion', 'event_date' => '2026-05-10'],
            [
                'contact_mobile' => '0920-555-0199',
                'contact_address' => 'Cebu City',
                'event_time' => '09:00:00',
                'priest_id' => $priest2->id,
                'location_id' => $mainSanctuary?->id,
                'status' => 'completed',
                'details' => [
                    'booking_mode' => 'individual',
                    'child_name' => 'Sofia Grace Tan',
                    'parent_guardian_name' => 'Michael Tan',
                    'parish_or_school_program' => 'Sacramenta Parish Catechism Program',
                ],
                'offering_amount' => 300,
                'payment_status' => 'paid',
                'amount_paid' => 300,
                'receipt_number' => 'OR-2026-0199',
                'payment_date' => '2026-05-05',
            ]
        );

        // First Communion — school batch, archived
        Reservation::firstOrCreate(
            ['contact_name' => 'Holy Trinity School', 'type' => 'first_communion', 'event_date' => '2026-02-14'],
            [
                'contact_mobile' => '0921-555-0120',
                'contact_address' => 'Cebu City',
                'event_time' => '10:00:00',
                'priest_id' => $priest->id,
                'location_id' => $mainSanctuary?->id,
                'status' => 'archived',
                'archive_reason' => 'completed',
                'details' => [
                    'booking_mode' => 'school_batch',
                    'school_name' => 'Holy Trinity School',
                    'students' => [
                        ['name' => 'Patricia Anne Go'],
                        ['name' => 'Miguel Angelo Reyes'],
                        ['name' => 'Kristine Joy Villanueva'],
                    ],
                ],
                'offering_amount' => 1000,
                'payment_status' => 'paid',
                'amount_paid' => 1000,
                'receipt_number' => 'OR-2026-0120',
                'payment_date' => '2026-02-10',
            ]
        );

        // Non-certificate types, to show the "no Print Certificate link" gating in the same list.
        Reservation::firstOrCreate(
            ['contact_name' => 'Villareal Family', 'type' => 'house_blessing', 'event_date' => '2026-06-01'],
            [
                'contact_mobile' => '0917-555-0142',
                'contact_address' => 'Cebu City',
                'event_time' => '15:00:00',
                'priest_id' => $priest->id,
                'status' => 'completed',
                'details' => [],
                'payment_status' => 'waived',
            ]
        );

        Reservation::firstOrCreate(
            ['contact_name' => 'Mercado Family', 'type' => 'pamisa_sa_kalag', 'event_date' => '2026-01-30'],
            [
                'contact_mobile' => '0917-555-0155',
                'contact_address' => 'Cebu City',
                'event_time' => '07:00:00',
                'priest_id' => $priest2->id,
                'location_id' => $parishHall?->id,
                'status' => 'archived',
                'archive_reason' => 'completed',
                'details' => ['names' => ['Pedro Mercado', 'Lourdes Mercado']],
                'offering_amount' => 200,
                'payment_status' => 'paid',
                'amount_paid' => 200,
                'receipt_number' => 'OR-2026-0155',
                'payment_date' => '2026-01-28',
            ]
        );
    }
}