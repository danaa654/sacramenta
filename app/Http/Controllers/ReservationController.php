<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Mail\ReservationConfirmed;
use App\Models\Location;
use App\Models\Priest;
use App\Models\Reservation;
use App\Services\NotificationDispatcher;
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
        protected SchedulingConflictService $conflicts,
        protected NotificationDispatcher $notifier
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
        $showRegularMasses = $request->boolean('show_regular_masses');
        $showPastRecords = $request->boolean('show_past_records');

        $reservations = Reservation::with('priest')
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            // Auto-generated regular Masses (from the weekly MassSchedule
            // templates, see GenerateMassSchedule) are staff's standing
            // schedule, not requests needing review — they have their own
            // dedicated "unassigned Masses" view. Hidden here by default so
            // they don't drown out actual staff-entered bookings; opt back
            // in with ?show_regular_masses=1.
            ->when(! $showRegularMasses, fn ($q) => $q->whereNull('mass_schedule_id'))
            // Completed and archived reservations are done — they already
            // have a dedicated read-only history view (Archives). Leaving
            // them in this list too meant every finished record sat here
            // forever, mixed in with the ones staff actually still need to
            // act on. Hidden by default; opt back in with
            // ?show_past_records=1 (an explicit status filter, e.g.
            // ?status=completed, also still reaches them directly).
            ->when(
                ! $showPastRecords && ! $request->string('status')->toString(),
                fn ($q) => $q->whereNotIn('status', ['completed', 'archived'])
            )
            ->orderByDesc('event_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'filters' => $request->only(['type', 'status']),
            'showRegularMasses' => $showRegularMasses,
            'showPastRecords' => $showPastRecords,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Reservations/Create', [
            'priests' => Priest::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(['id', 'name']),
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

        $this->notifier->notifyAdmins(
            kind: 'new_reservation',
            title: 'New reservation created',
            body: "{$reservation->contact_name}'s ".str_replace('_', ' ', $reservation->type)." on {$reservation->event_date->format('M j')} needs review.",
            reservation: $reservation,
            except: $request->user()
        );

        return redirect()->route('reservations.index')
            ->with('success', 'Reservation created.');
    }

    public function show(Reservation $reservation): Response
    {
        $reservation->load('priest', 'location', 'requirements', 'rotaAssignments');

        return Inertia::render('Reservations/Show', [
            'reservation' => $reservation,
            'priests' => Priest::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Printable Official Receipt for whatever has been recorded against this
     * reservation's offering/stipend so far (amount_paid, receipt_number,
     * payment_status, etc.). Plain Blade + window.print() rather than a PDF
     * library — no extra dependency, and staff can still "Save as PDF" from
     * the browser's print dialog if they want a file.
     */
    public function receipt(Reservation $reservation)
    {
        return view('receipts.reservation', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * Which reservation types have a printable sacramental certificate, and
     * which Blade view renders it. Only sacraments that produce an actual
     * paper certificate belong here — most reservation types (blessings,
     * Masses, etc.) have no certificate to print.
     */
    protected array $certificateViews = [
        'wedding' => 'certificates.wedding',
        'baptism' => 'certificates.baptism',
        'first_communion' => 'certificates.first_communion',
        'burial' => 'certificates.burial',
    ];

    /**
     * Printable sacramental certificate (Marriage, Baptismal, First
     * Communion, or Death/Burial) for this reservation. Plain Blade +
     * window.print(), same approach as the Official Receipt — no PDF
     * library dependency, and staff can "Save as PDF" from the browser's
     * print dialog if they need a file.
     */
    public function certificate(Reservation $reservation)
    {
        abort_unless(isset($this->certificateViews[$reservation->type]), 404);

        return view($this->certificateViews[$reservation->type], [
            'reservation' => $reservation,
        ]);
    }

    public function edit(Reservation $reservation): Response
    {
        return Inertia::render('Reservations/Edit', [
            'reservation' => $reservation,
            'priests' => Priest::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(['id', 'name']),
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

    public function destroy(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->notifier->notifyAdmins(
            kind: 'cancelled',
            title: 'Reservation cancelled',
            body: "{$reservation->contact_name}'s ".str_replace('_', ' ', $reservation->type)." on {$reservation->event_date->format('M j')} was cancelled.",
            except: $request->user()
        );

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

        $completedLockBlocker = $this->completedLockBlocker($reservation, $validated['status']);

        if ($completedLockBlocker) {
            return back()->withErrors(['status' => $completedLockBlocker]);
        }

        $blocker = $this->confirmationBlocker($reservation, $validated['status']);

        if ($blocker) {
            return back()->withErrors(['status' => $blocker]);
        }

        $wasConfirmed = $reservation->status === 'confirmed';
        $archiveReason = $this->resolveArchiveReason($reservation, $validated['status']);

        $reservation->update([
            'status' => $validated['status'],
            'archive_reason' => $archiveReason,
        ]);

        if ($validated['status'] === 'confirmed' && ! $wasConfirmed) {
            $this->handleNewlyConfirmed($reservation, $request);
        }

        return back()->with('success', 'Reservation status updated.');
    }

    /**
     * Backs the "Reservation Actions" card on the Show page: lets the admin
     * reassign the priest, change status, and change payment status all in
     * one save, from a single sidebar form. Runs the same confirm-time
     * validation (requirements checklist + priest/chapel/venue conflicts)
     * as updateStatus() whenever the status is moving into 'confirmed'.
     */
    public function updateActions(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'priest_id' => ['nullable', Rule::exists('priests', 'id')],
            'status' => ['required', Rule::in(['draft', 'confirmed', 'completed', 'archived'])],
            'payment_status' => ['required', Rule::in(['unpaid', 'partial', 'paid', 'waived'])],
        ]);

        // Apply the priest reassignment before running confirm-time conflict
        // checks, so "assign priest + confirm" in one click is checked
        // against the priest that's about to be saved, not the old one.
        $reservation->priest_id = $validated['priest_id'] ?? null;

        $completedLockBlocker = $this->completedLockBlocker($reservation, $validated['status']);

        if ($completedLockBlocker) {
            return back()->withErrors(['status' => $completedLockBlocker]);
        }

        $blocker = $this->confirmationBlocker($reservation, $validated['status']);

        if ($blocker) {
            return back()->withErrors(['status' => $blocker]);
        }

        $wasConfirmed = $reservation->getOriginal('status') === 'confirmed';
        $archiveReason = $this->resolveArchiveReason($reservation, $validated['status']);

        $reservation->status = $validated['status'];
        $reservation->archive_reason = $archiveReason;
        $reservation->payment_status = $validated['payment_status'];

        // The Financials "Record Payment" drawer always asks for Status and
        // Amount Paid together, so those two can never drift apart there.
        // This sidebar only has a Payment dropdown, though — without this,
        // picking "Paid" here would flip the status flag while silently
        // leaving amount_paid at 0, showing "Paid" next to a full balance
        // still outstanding. Auto-fill the amount whenever marking Paid
        // would otherwise leave it under the offering; never reduce an
        // amount that's already recorded (e.g. an overpayment).
        if ($validated['payment_status'] === 'paid') {
            $reservation->amount_paid = max(
                (float) $reservation->amount_paid,
                (float) ($reservation->offering_amount ?? 0)
            );
        }

        $reservation->save();

        if ($validated['status'] === 'confirmed' && ! $wasConfirmed) {
            $this->handleNewlyConfirmed($reservation, $request);
        }

        return back()->with('success', 'Reservation updated.');
    }

    /**
     * 'archived' collapses two different situations — cancelled before it
     * happened, or completed and later filed into history — into one
     * status value. This derives which one just happened from the
     * reservation's status *before* this update, so the distinction is
     * recorded automatically without adding another field for staff to
     * fill in. Non-archive transitions clear it, so a record that leaves
     * Archives (edge case, but possible via direct status changes) doesn't
     * carry a stale reason.
     */
    protected function resolveArchiveReason(Reservation $reservation, string $newStatus): ?string
    {
        if ($newStatus !== 'archived') {
            return null;
        }

        if ($reservation->status === 'archived') {
            // Already archived and staying archived (e.g. Save Changes with
            // no actual status change) — keep whatever reason it already has.
            return $reservation->archive_reason;
        }

        return $reservation->status === 'completed' ? 'completed' : 'cancelled';
    }

    /**
     * A completed sacrament already happened — reopening it to Draft or
     * Confirmed would misrepresent the record, and "Cancelled" doesn't
     * make sense for something that's done. The only place a Completed
     * reservation is allowed to go is Archived (filed into history).
     * Mirrors the Status dropdown being locked on the Show page, but
     * enforced here too since that's just a UI convenience.
     */
    protected function completedLockBlocker(Reservation $reservation, string $newStatus): ?string
    {
        if ($reservation->status !== 'completed' || $newStatus === 'completed') {
            return null;
        }

        if ($newStatus === 'archived') {
            return null;
        }

        return 'This reservation is already completed — it can only be moved to Archived from here, not reopened.';
    }

    /**
     * Draft -> Confirmed is blocked unless every checklist item for the
     * reservation's type has been checked off, AND its date/time doesn't
     * collide with something else that's already confirmed — a draft may
     * have sat around while another reservation for the same priest, chapel
     * slot, or venue got confirmed in the meantime. Returns an error message
     * to show the admin, or null when the transition is allowed (including
     * when $newStatus isn't 'confirmed', or the reservation is already
     * confirmed).
     */
    protected function confirmationBlocker(Reservation $reservation, string $newStatus): ?string
    {
        if ($newStatus !== 'confirmed' || $reservation->status === 'confirmed') {
            return null;
        }

        $reservation->loadMissing('requirements');
        $missing = $reservation->incompleteRequirementLabels();

        if (! empty($missing)) {
            return 'Cannot confirm this reservation — still missing: '.implode(', ', $missing).'.';
        }

        if (! $reservation->event_time) {
            return null;
        }

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

                return "Cannot confirm — {$priestName} was already confirmed for {$conflictTime} on the same date by another reservation.";
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

                return "Cannot confirm — {$chapel} was already confirmed for {$conflictTime} on the same date by another reservation.";
            }
        }

        if ($reservation->location_id) {
            $conflict = $this->conflicts->findLocationConflict(
                $reservation->location_id,
                $reservation->event_date->format('Y-m-d'),
                substr((string) $reservation->event_time, 0, 5),
                $reservation->type,
                $reservation->id
            );

            if ($conflict) {
                $locationName = $reservation->location?->name ?? 'This venue';
                $conflictTime = \Carbon\Carbon::parse($conflict->event_time)->format('g:i A');

                return "Cannot confirm — {$locationName} was already confirmed for {$conflictTime} on the same date by another reservation.";
            }
        }

        return null;
    }

    /**
     * Side effects that fire the first time a reservation transitions into
     * 'confirmed': seed the rota/volunteer slots, notify the other admins,
     * and email the contact person. The calendar itself needs no separate
     * sync step — the Calendar page reads reservations live, so saving the
     * status change above is what "creates/updates the calendar event".
     */
    protected function handleNewlyConfirmed(Reservation $reservation, Request $request): void
    {
        $this->seedRota($reservation);

        $this->notifier->notifyAdmins(
            kind: 'confirmed',
            title: 'Reservation confirmed',
            body: "{$reservation->contact_name}'s ".str_replace('_', ' ', $reservation->type)." on {$reservation->event_date->format('M j')} is now confirmed.",
            reservation: $reservation,
            except: $request->user()
        );

        if ($reservation->contact_email) {
            Mail::to($reservation->contact_email)
                ->send(new ReservationConfirmed($reservation->loadMissing('priest')));
        }
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
            'type' => ['nullable', 'string'],
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

        // Wedding, Baptism, and Burial all share the single Main Sanctuary
        // venue, so any confirmed reservation of these three types on the
        // same date blocks a slot for the others too — same idea as the
        // per-priest / per-chapel checks above.
        $takenVenue = collect();

        if (in_array($validated['type'] ?? null, ['wedding', 'baptism', 'burial'], true)) {
            $mainSanctuaryId = Location::where('name', 'Main Sanctuary')->value('id');

            if ($mainSanctuaryId) {
                $takenVenue = Reservation::query()
                    ->where('location_id', $mainSanctuaryId)
                    ->whereIn('type', ['wedding', 'baptism', 'burial'])
                    ->where('status', 'confirmed')
                    ->whereDate('event_date', $validated['date'])
                    ->whereNotNull('event_time')
                    ->when($validated['exclude'] ?? null, fn ($q, $excludeId) => $q->where('id', '!=', $excludeId))
                    ->get()
                    ->map(fn (Reservation $r) => substr((string) $r->event_time, 0, 5))
                    ->values();
            }
        }

        return response()->json([
            'taken' => $taken,
            'takenChapel' => $takenChapel,
            'takenVenue' => $takenVenue,
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

        if (empty($items)) {
            return;
        }

        if ($reservation->type === 'baptism' && ($reservation->details['baptism_type'] ?? null) === 'group') {
            $children = $reservation->details['children'] ?? [];

            foreach ($children as $index => $child) {
                foreach ($items as $item) {
                    $reservation->requirements()->create([
                        'child_index' => $index,
                        'child_name' => $child['child_name'] ?? "Child ".($index + 1),
                        'key' => $item['key'],
                        'label' => $item['label'],
                        'is_completed' => false,
                    ]);
                }
            }

            return;
        }

        foreach ($items as $item) {
            $reservation->requirements()->create([
                'key' => $item['key'],
                'label' => $item['label'],
                'is_completed' => false,
            ]);
        }
    }

    /**
     * Build rota/volunteer slots for a reservation once it's CONFIRMED,
     * per config/rota_roles.php. Runs only the first time a reservation
     * transitions into 'confirmed' (guarded by $wasConfirmed in the
     * caller), and is idempotent per role/slot thanks to the unique
     * (reservation_id, role_key, slot_number) index — firstOrCreate skips
     * any slot that's already there.
     */
    protected function seedRota(Reservation $reservation): void
    {
        $roles = config("rota_roles.{$reservation->type}", []);

        foreach ($roles as $role) {
            for ($slot = 1; $slot <= ($role['count'] ?? 1); $slot++) {
                $reservation->rotaAssignments()->firstOrCreate(
                    ['role_key' => $role['key'], 'slot_number' => $slot],
                    ['role_label' => $role['label'], 'status' => 'needed']
                );
            }
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