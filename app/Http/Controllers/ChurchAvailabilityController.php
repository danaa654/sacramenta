<?php

namespace App\Http\Controllers;

use App\Services\ChurchAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the live "Church Availability" panel in ReservationForm.vue:
 * the day's full occupied/available timeline, an immediate conflict check
 * for whatever date/time/type is currently selected, and nearest-available
 * suggestions when there's a conflict. See App\Services\ChurchAvailabilityService
 * for the actual engine.
 */
class ChurchAvailabilityController extends Controller
{
    public function __construct(protected ChurchAvailabilityService $engine)
    {
    }

    /**
     * GET /church-availability
     * ?date=YYYY-MM-DD&type=wedding[&time=HH:MM][&exclude=ID][&location_id=ID]
     */
    public function day(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['nullable', 'string'],
            'time' => ['nullable', 'date_format:H:i'],
            'exclude' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            // JSON-encoded subset of the in-progress reservation's `details`
            // (ceremony_type / baptism_type / children / booking_mode /
            // students) — just enough for ReservationDuration to size a
            // Wedding/Baptism/First Communion slot correctly before it's
            // saved. Optional: falls back to the flat per-type duration
            // when absent, same as before this existed.
            'details' => ['nullable', 'string'],
        ]);

        $date = $validated['date'];
        $type = $validated['type'] ?? null;
        $excludeId = $validated['exclude'] ?? null;
        $locationId = $validated['location_id'] ?? null;
        $details = json_decode($validated['details'] ?? '', true) ?: [];

        $blocked = $this->engine->isBlocked($date, $locationId);

        $response = [
            'date' => $date,
            'blocked' => $blocked ? [
                'title' => $blocked->title,
                'reason' => $blocked->reason,
                'start_date' => $blocked->start_date->toDateString(),
                'end_date' => $blocked->end_date->toDateString(),
            ] : null,
            'timeline' => $this->engine->dayTimeline($date, $excludeId, $locationId),
            // The Event Time dropdown's only source of selectable options —
            // administrators may never type an arbitrary time. Empty when
            // no type is known yet, or when the date is blocked.
            'available_slots' => $type ? $this->engine->availableSlots($date, $type, $excludeId, $locationId, 15, $details) : [],
            'conflict' => null,
            'suggestions' => [],
        ];

        if ($type && ($validated['time'] ?? null)) {
            $conflict = $this->engine->findConflict($date, $validated['time'], $type, $excludeId, $locationId, $details);

            if ($conflict) {
                $response['conflict'] = [
                    'type' => $conflict['type'],
                    'label' => $conflict['label'],
                    'start_label' => $conflict['start']->format('g:i A'),
                    'end_label' => $conflict['end']->format('g:i A'),
                    'reservation_id' => $conflict['reservation_id'],
                ];

                $response['suggestions'] = $this->engine->suggestSlots($date, $type, $excludeId, $locationId, 3, 14, $details);
            }
        } elseif ($blocked) {
            // Blocked date with no specific time picked yet — still worth
            // surfacing nearby alternatives if a type is known.
            $response['suggestions'] = $type
                ? $this->engine->suggestSlots($date, $type, $excludeId, $locationId, 3, 14, $details)
                : [];
        }

        return response()->json($response);
    }
}