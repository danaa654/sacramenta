<?php

namespace Tests\Unit;

use App\Models\Location;
use App\Models\Reservation;
use App\Services\ChurchAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the 15 test cases from the time-conflict-prevention spec, run
 * directly against App\Services\ChurchAvailabilityService::findConflict() —
 * the centralized engine every reservation/mass-schedule write path is
 * supposed to go through.
 *
 * Test numbers in each method name/docblock match the spec's own numbering
 * (section 18, "TEST BEFORE FINISHING") so a failure here maps directly
 * back to the requirement it covers.
 *
 * Every "existing" event is created directly via Reservation::create()
 * with an explicit details.duration_minutes, which ReservationDuration
 * honors as an override — this lets each test pin down an exact,
 * predictable window instead of depending on config/reservation_requirements.php's
 * flat per-type defaults.
 */
class ChurchAvailabilityServiceOverlapTest extends TestCase
{
    use RefreshDatabase;

    protected ChurchAvailabilityService $engine;

    protected Location $mainSanctuary;

    protected string $date = '2026-09-01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(ChurchAvailabilityService::class);

        $this->mainSanctuary = Location::create([
            'name' => config('church_schedule.main_sanctuary_name', 'Parish of the Holy Sacraments'),
            'kind' => 'main_sanctuary',
            'is_active' => true,
        ]);
    }

    protected function makeReservation(
        string $type,
        string $time,
        int $durationMinutes,
        string $status = 'confirmed',
        array $extraDetails = []
    ): Reservation {
        return Reservation::create([
            'type' => $type,
            'contact_name' => ucfirst(str_replace('_', ' ', $type)).' Test',
            'contact_mobile' => '09171234567',
            'event_date' => $this->date,
            'event_time' => $time,
            'location_id' => $this->mainSanctuary->id,
            'status' => $status,
            'details' => array_merge(['duration_minutes' => $durationMinutes], $extraDetails),
        ]);
    }

    protected function assertBlocked(string $type, string $time, int $durationMinutes, ?int $excludeId = null): void
    {
        $conflict = $this->engine->findConflict(
            $this->date, $time, $type, $excludeId, $this->mainSanctuary->id,
            ['duration_minutes' => $durationMinutes]
        );

        $this->assertNotNull($conflict, "Expected a conflict for {$type} at {$time} ({$durationMinutes}m) but none was found.");
    }

    protected function assertAllowed(string $type, string $time, int $durationMinutes, ?int $excludeId = null): void
    {
        $conflict = $this->engine->findConflict(
            $this->date, $time, $type, $excludeId, $this->mainSanctuary->id,
            ['duration_minutes' => $durationMinutes]
        );

        $this->assertNull($conflict, "Expected no conflict for {$type} at {$time} ({$durationMinutes}m) but found one.");
    }

    /** TEST 1 — Partial overlap: Wedding 10:00–11:30, Baptism 11:00–11:30 -> BLOCK */
    public function test_1_partial_overlap_is_blocked(): void
    {
        $this->makeReservation('wedding', '10:00', 90);

        $this->assertBlocked('baptism', '11:00', 30);
    }

    /** TEST 2 — New event starts during existing event -> BLOCK */
    public function test_2_new_event_starting_during_existing_is_blocked(): void
    {
        $this->makeReservation('wedding', '10:00', 90);

        $this->assertBlocked('baptism', '11:15', 30);
    }

    /** TEST 2b (spec item 3) — New event ends during existing event -> BLOCK */
    public function test_3_new_event_ending_during_existing_is_blocked(): void
    {
        $this->makeReservation('wedding', '10:00', 90);

        // 9:45–10:15 ends inside the 10:00–11:30 window.
        $this->assertBlocked('baptism', '09:45', 30);
    }

    /** TEST 4 — New event completely contains existing event -> BLOCK */
    public function test_4_new_event_containing_existing_is_blocked(): void
    {
        $this->makeReservation('baptism', '10:30', 30); // 10:30–11:00

        $this->assertBlocked('wedding', '10:00', 90); // 10:00–11:30
    }

    /** TEST 5 (spec item 5) — Existing event completely contains new event -> BLOCK */
    public function test_5_existing_event_containing_new_is_blocked(): void
    {
        $this->makeReservation('wedding', '10:00', 90); // 10:00–11:30

        $this->assertBlocked('baptism', '10:30', 30); // 10:30–11:00
    }

    /** TEST (spec item 4 in section 3) — Same start time -> BLOCK */
    public function test_same_start_time_is_blocked(): void
    {
        $this->makeReservation('wedding', '10:00', 90);

        $this->assertBlocked('baptism', '10:00', 30);
    }

    /** TEST 7 — One-minute overlap -> BLOCK, even a single minute */
    public function test_7_one_minute_overlap_is_blocked(): void
    {
        $this->makeReservation('wedding', '10:00', 90); // 10:00–11:30

        $this->assertBlocked('baptism', '11:29', 31); // 11:29–12:00
    }

    /** TEST (section 3, item 5) — Exact end/start boundary -> ALLOW */
    public function test_exact_end_start_boundary_is_allowed(): void
    {
        $this->makeReservation('wedding', '10:00', 90); // 10:00–11:30

        $this->assertAllowed('baptism', '11:30', 30); // 11:30–12:00
    }

    /** TEST 8 (section 3, item 6) — Completely separate events -> ALLOW */
    public function test_8_completely_separate_events_are_allowed(): void
    {
        $this->makeReservation('wedding', '10:00', 90);

        $this->assertAllowed('baptism', '12:00', 30);
    }

    /** TEST 9 — Different dates -> ALLOW (never even queried as "same day") */
    public function test_9_different_dates_are_allowed(): void
    {
        $this->makeReservation('wedding', '10:00', 90);

        $conflict = $this->engine->findConflict(
            '2026-09-02', '10:00', 'baptism', null, $this->mainSanctuary->id,
            ['duration_minutes' => 30]
        );

        $this->assertNull($conflict);
    }

    /** TEST 10 — Cancelled existing event never blocks -> ALLOW */
    public function test_10_cancelled_existing_event_is_allowed(): void
    {
        $this->makeReservation('wedding', '10:00', 90, status: 'cancelled');

        $this->assertAllowed('baptism', '10:00', 30);
    }

    /** TEST 11 — Editing the same event, unchanged, never conflicts with itself -> ALLOW */
    public function test_11_editing_same_event_excludes_itself(): void
    {
        $wedding = $this->makeReservation('wedding', '10:00', 90);

        $this->assertAllowed('wedding', '10:00', 90, excludeId: $wedding->id);
    }

    /** TEST 12 — Editing an event INTO another event's time -> BLOCK */
    public function test_12_editing_into_another_events_time_is_blocked(): void
    {
        $wedding = $this->makeReservation('wedding', '09:00', 90); // originally 9:00–10:30
        $this->makeReservation('baptism', '11:00', 30); // 11:00–11:30, someone else's slot

        // Admin now tries to move the wedding to 11:00–12:30 — collides
        // with the baptism above. The wedding's own (soon-to-be-replaced)
        // 9:00 slot is excluded via excludeId, but the baptism is not.
        $this->assertBlocked('wedding', '11:00', 90, excludeId: $wedding->id);
    }

    /** TEST 13 — Mass Schedule overlapping another Mass Schedule -> BLOCK */
    public function test_13_mass_overlapping_mass_is_blocked(): void
    {
        $this->makeReservation('mass', '10:00', 60); // 10:00–11:00

        $this->assertBlocked('mass', '10:30', 60); // 10:30–11:30
    }

    /** TEST 14 — Reservation overlapping Mass Schedule -> BLOCK */
    public function test_14_reservation_overlapping_mass_schedule_is_blocked(): void
    {
        $this->makeReservation('mass', '10:00', 60); // 10:00–11:00

        $this->assertBlocked('wedding', '10:30', 90); // 10:30–12:00
    }

    /** TEST 15 — Mass Schedule and reservation starting exactly when the previous ends -> ALLOW */
    public function test_15_mass_and_reservation_back_to_back_are_allowed(): void
    {
        $this->makeReservation('wedding', '10:00', 90); // 10:00–11:30

        $this->assertAllowed('mass', '11:30', 60); // 11:30–12:30
    }

    /**
     * Regression guard for the bug fixed in StoreReservationRequest::
     * checkChurchAvailability() — findConflict() must be called WITH the
     * details payload, or duration/venue resolution silently falls back
     * to flat defaults. This proves the engine itself handles variant
     * durations correctly when details ARE passed (a group baptism runs
     * longer than the 60-minute default).
     */
    public function test_variant_duration_from_details_is_honored(): void
    {
        // Group baptism: base 30 + 15/child * 4 children = 90 minutes
        // (see config/reservation_requirements.php durations_baptism.group).
        $groupDuration = config('reservation_requirements.durations_baptism.group.base', 30)
            + 4 * config('reservation_requirements.durations_baptism.group.per_child', 15);

        $this->makeReservation('baptism', '10:00', $groupDuration, extraDetails: [
            'baptism_type' => 'group',
            'children' => array_fill(0, 4, ['child_name' => 'Child']),
        ]);

        // A wedding requested right after the flat 60-minute default would
        // have ended (11:00) but BEFORE the real, longer group-baptism
        // window actually ends — must still be blocked.
        $this->assertBlocked('wedding', '11:00', 90);
    }
}