<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Mail\ReservationConfirmed;
use App\Models\Priest;
use App\Models\Reservation;
use App\Services\SchedulingConflictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function __construct(
        protected SchedulingConflictService $conflicts
    ) {
    }

    /**
     * Kapilya / Barangay chapel options for the Chapel Mass reservation type.
     * Move this to a config file or table later if parishes need to manage it themselves.
     */
    protected array $chapels = [
        'San Isidro Chapel',
        'Sto. Niño Chapel',
        'Our Lady of Fatima Chapel',
        'San Roque Chapel',
        'Sacred Heart Chapel',
    ];

    public function index(Request $request): Response
    {
        $reservations = Reservation::with('priest')
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('event_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'filters' => $request->only(['type', 'status']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Reservations/Create', [
            'priests' => Priest::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'chapels' => $this->chapels,
            // Populated when arriving from the Calendar page's "click an empty day" flow.
            'date' => $request->query('date'),
        ]);
    }

    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['details'] = $this->cleanDetails($data['type'], $data['details'] ?? []);
        $data['status'] = 'draft';

        $reservation = Reservation::create($data);

        $this->seedRequirements($reservation);

        return redirect()->route('reservations.index')
            ->with('success', 'Reservation created.');
    }

    public function show(Reservation $reservation): Response
    {
        $reservation->load('priest', 'requirements');

        return Inertia::render('Reservations/Show', [
            'reservation' => $reservation,
        ]);
    }

    public function edit(Reservation $reservation): Response
    {
        return Inertia::render('Reservations/Edit', [
            'reservation' => $reservation,
            'priests' => Priest::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'chapels' => $this->chapels,
        ]);
    }

    public function update(StoreReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $data = $request->validated();
        $data['details'] = $this->cleanDetails($data['type'], $data['details'] ?? []);

        $reservation->update($data);

        return redirect()->route('reservations.index')
            ->with('success', 'Reservation updated.');
    }

    public function destroy(Reservation $reservation): RedirectResponse
    {
        $reservation->delete();

        return redirect()->route('reservations.index')
            ->with('success', 'Reservation deleted.');
    }

    /**
     * Toggle/annotate checklist items for a reservation. Not exposed during
     * initial Create — office staff fill this in afterward from Show/Edit.
     */
    public function updateRequirements(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:reservation_requirements,id'],
            'items.*.is_completed' => ['required', 'boolean'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated['items'] as $item) {
            $reservation->requirements()
                ->where('id', $item['id'])
                ->update([
                    'is_completed' => $item['is_completed'],
                    'note' => $item['note'] ?? null,
                ]);
        }

        return back()->with('success', 'Requirements updated.');
    }

    /**
     * Explicit status transition endpoint. Draft -> Confirmed is blocked
     * server-side (not just in the UI) unless every checklist item for the
     * reservation's type has been checked off, AND its date/time doesn't
     * collide with something else that's already confirmed — a draft may
     * have sat around while another reservation for the same priest or
     * chapel slot got confirmed in the meantime.
     */
    public function updateStatus(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'confirmed', 'completed', 'archived'])],
        ]);

        $wasConfirmed = $reservation->status === 'confirmed';

        if ($validated['status'] === 'confirmed' && $reservation->status !== 'confirmed') {
            $reservation->loadMissing('requirements');
            $missing = $reservation->incompleteRequirementLabels();

            if (! empty($missing)) {
                return back()->withErrors([
                    'status' => 'Cannot confirm this reservation — still missing: '.implode(', ', $missing).'.',
                ]);
            }

            if ($reservation->event_time) {
                if ($reservation->priest_id) {
                    $conflict = $this->conflicts->findPriestConflict(
                        $reservation->priest_id,
                        $reservation->event_date->format('Y-m-d'),
                        substr((string) $reservation->event_time, 0, 5),
                        $reservation->type,
                        $reservation->id
                    );

                    if ($conflict) {
                        $priestName = $reservation->priest?->name ?? 'This priest';
                        $conflictTime = \Carbon\Carbon::parse($conflict->event_time)->format('g:i A');

                        return back()->withErrors([
                            'status' => "Cannot confirm — {$priestName} was already confirmed for {$conflictTime} on the same date by another reservation.",
                        ]);
                    }
                }

                $chapel = $reservation->details['chapel'] ?? null;

                if ($reservation->type === 'chapel_mass' && $chapel) {
                    $conflict = $this->conflicts->findChapelConflict(
                        $chapel,
                        $reservation->event_date->format('Y-m-d'),
                        substr((string) $reservation->event_time, 0, 5),
                        $reservation->type,
                        $reservation->id
                    );

                    if ($conflict) {
                        $conflictTime = \Carbon\Carbon::parse($conflict->event_time)->format('g:i A');

                        return back()->withErrors([
                            'status' => "Cannot confirm — {$chapel} was already confirmed for {$conflictTime} on the same date by another reservation.",
                        ]);
                    }
                }
            }
        }

        $reservation->update(['status' => $validated['status']]);

        if ($validated['status'] === 'confirmed' && ! $wasConfirmed && $reservation->contact_email) {
            Mail::to($reservation->contact_email)
                ->send(new ReservationConfirmed($reservation->loadMissing('priest')));
        }

        return back()->with('success', 'Reservation status updated.');
    }

    /**
     * GET /reservations/availability?priest_id=X&date=Y[&exclude=Z][&chapel=C&type=chapel_mass]
     *
     * Returns the list of "HH:MM" slots already taken by CONFIRMED
     * reservations for that priest on that date, so the create/edit form
     * can grey them out before the user submits. `exclude` lets the edit
     * form ignore the reservation currently being edited. When `chapel`
     * is also supplied (Chapel Mass bookings), a second list of slots
     * already taken at that chapel is returned too.
     */
    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'priest_id' => ['nullable', 'integer', 'exists:priests,id'],
            'date' => ['required', 'date'],
            'exclude' => ['nullable', 'integer'],
            'chapel' => ['nullable', 'string'],
        ]);

        $taken = collect();

        if (! empty($validated['priest_id'])) {
            $taken = Reservation::query()
                ->where('priest_id', $validated['priest_id'])
                ->where('status', 'confirmed')
                ->whereDate('event_date', $validated['date'])
                ->whereNotNull('event_time')
                ->when($validated['exclude'] ?? null, fn ($q, $excludeId) => $q->where('id', '!=', $excludeId))
                ->get()
                ->map(fn (Reservation $r) => substr((string) $r->event_time, 0, 5))
                ->values();
        }

        $takenChapel = collect();

        if (! empty($validated['chapel'])) {
            $takenChapel = Reservation::query()
                ->where('type', 'chapel_mass')
                ->where('details->chapel', $validated['chapel'])
                ->where('status', 'confirmed')
                ->whereDate('event_date', $validated['date'])
                ->whereNotNull('event_time')
                ->when($validated['exclude'] ?? null, fn ($q, $excludeId) => $q->where('id', '!=', $excludeId))
                ->get()
                ->map(fn (Reservation $r) => substr((string) $r->event_time, 0, 5))
                ->values();
        }

        return response()->json([
            'taken' => $taken,
            'takenChapel' => $takenChapel,
        ]);
    }

    /**
     * Build the checklist rows for a freshly-created reservation based on
     * its type, per config/reservation_requirements.php. Types with no
     * defined checklist simply get no rows (and are treated as already
     * confirmable — see Reservation::requirementsComplete()).
     */
    protected function seedRequirements(Reservation $reservation): void
    {
        $items = config("reservation_requirements.checklists.{$reservation->type}", []);

        foreach ($items as $item) {
            $reservation->requirements()->create([
                'key' => $item['key'],
                'label' => $item['label'],
                'is_completed' => false,
            ]);
        }
    }

    /**
     * Normalize the type-specific "details" payload before it's stored as JSON,
     * e.g. turning the Pamisa sa Kalag textarea into a clean array of names.
     */
    protected function cleanDetails(string $type, array $details): array
    {
        if ($type === 'pamisa_sa_kalag' && isset($details['names'])) {
            $details['names'] = collect(explode("\n", $details['names']))
                ->map(fn ($name) => trim($name))
                ->filter()
                ->values()
                ->all();
        }

        if ($type === 'baptism' && isset($details['godparents'])) {
            $details['godparents'] = collect($details['godparents'])
                ->filter(fn ($g) => filled($g['name'] ?? null))
                ->values()
                ->all();
        }

        return $details;
    }
}