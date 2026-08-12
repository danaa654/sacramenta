<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the bug fixed in StoreReservationRequest::
 * checkChurchAvailability(): the call to ChurchAvailabilityService::
 * findConflict() was missing its $details argument, which silently broke
 * venue resolution for Chapel Mass (needs details.chapel) and School Mass
 * (needs details.venue) — those two types could never be flagged as
 * conflicting with anything on submit, regardless of the frontend's own
 * (correct) live-validation warning.
 *
 * This hits the real POST /reservations endpoint end-to-end (not just the
 * service directly, like ChurchAvailabilityServiceOverlapTest) so a future
 * regression here — e.g. someone "simplifying" the call and dropping the
 * argument again — fails a test that exercises the actual submit path.
 */
class ReservationChurchAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function validChapelMassPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'chapel_mass',
            'contact_name' => 'Barangay Chapel Coordinator',
            'contact_mobile' => '09171234567',
            'event_date' => now()->addDays(7)->toDateString(),
            'event_time' => '10:00',
            'details' => ['chapel' => 'San Isidro Chapel'],
        ], $overrides);
    }

    public function test_overlapping_chapel_mass_at_the_same_chapel_is_rejected(): void
    {
        $user = User::factory()->create();

        // Chapel Mass has no entry in config('reservation_requirements.durations')
        // and falls back to the 'default' (30 minutes) — so this occupies
        // 10:00–10:30, not the 90 minutes a Wedding would.
        $this->actingAs($user)
            ->post(route('reservations.store'), $this->validChapelMassPayload())
            ->assertRedirect();

        // A second Chapel Mass at the SAME chapel, overlapping (10:15 falls
        // inside 10:00–10:30), must be rejected on submit — not just
        // flagged by the frontend's live-validation panel.
        $response = $this->actingAs($user)->post(route('reservations.store'), $this->validChapelMassPayload([
            'contact_name' => 'Second Coordinator',
            'event_time' => '10:15',
        ]));

        $response->assertSessionHasErrors('event_time');
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_chapel_mass_at_a_different_chapel_at_the_same_time_is_allowed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('reservations.store'), $this->validChapelMassPayload())
            ->assertRedirect();

        // Same date/time, but a DIFFERENT chapel — different venue key, so
        // no conflict, even though the naive (details-less) version of the
        // bug would have resolved both to no venue at all and let this
        // through for the wrong reason.
        $response = $this->actingAs($user)->post(route('reservations.store'), $this->validChapelMassPayload([
            'contact_name' => 'Other Chapel Coordinator',
            'details' => ['chapel' => 'Santo Niño Chapel'],
        ]));

        $response->assertSessionDoesntHaveErrors('event_time');
        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_non_admin_cannot_override_a_detected_conflict(): void
    {
        $staff = User::factory()->create(); // role defaults to 'staff'

        $this->actingAs($staff)
            ->post(route('reservations.store'), $this->validChapelMassPayload())
            ->assertRedirect();

        // 10:15 genuinely overlaps the first booking's 10:00–10:30 window
        // — without override_conflict this would be rejected by
        // checkChurchAvailability(); WITH it, a non-admin should be
        // stopped at authorize() before validation even runs.
        $response = $this->actingAs($staff)->post(route('reservations.store'), $this->validChapelMassPayload([
            'contact_name' => 'Second Coordinator',
            'event_time' => '10:15',
            'override_conflict' => 1,
            'override_reason' => 'I need this slot anyway.',
        ]));

        $response->assertForbidden();
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_admin_can_override_a_detected_conflict(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('reservations.store'), $this->validChapelMassPayload())
            ->assertRedirect();

        // Same genuinely-overlapping 10:15 window as the non-admin test
        // above — this proves the admin is pushing through a REAL detected
        // conflict, not just submitting a time that was never blocked.
        $response = $this->actingAs($admin)->post(route('reservations.store'), $this->validChapelMassPayload([
            'contact_name' => 'Second Coordinator',
            'event_time' => '10:15',
            'override_conflict' => 1,
            'override_reason' => 'Approved by the parish priest.',
        ]));

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('reservations', 2);
    }
}