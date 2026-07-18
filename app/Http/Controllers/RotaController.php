<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RotaController extends Controller
{
    /**
     * Bulk-update the volunteer name/status/note for a reservation's rota
     * slots, same "array of rows" pattern as
     * ReservationController::updateRequirements.
     */
    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:rota_assignments,id'],
            'items.*.volunteer_name' => ['nullable', 'string', 'max:255'],
            'items.*.status' => ['required', Rule::in(['needed', 'requested', 'confirmed'])],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated['items'] as $item) {
            $reservation->rotaAssignments()
                ->where('id', $item['id'])
                ->update([
                    'volunteer_name' => $item['volunteer_name'] ?? null,
                    'status' => $item['status'],
                    'note' => $item['note'] ?? null,
                ]);
        }

        return back()->with('success', 'Rota updated.');
    }
}