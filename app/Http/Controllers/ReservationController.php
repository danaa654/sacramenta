<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Mail\ReservationConfirmed;
use App\Models\Location;
use App\Models\MassSchedule;
use App\Models\Priest;
use App\Models\Reservation;
use App\Services\AuditLogger;
use App\Services\ChurchAvailabilityService;
use App\Services\MarriagePreparationSchedulingService;
use App\Services\NotificationDispatcher;
use App\Services\SchedulingConflictService;
use Carbon\Carbon;
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
        protected NotificationDispatcher $notifier,
        protected ChurchAvailabilityService $availabilityEngine
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

        $reservations = Reservation::with(['priest', 'linkedMass.priest'])
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->searchSubject($search))
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            // Mass Schedule entries (type = 'mass') are parish-created
            // Masses, not requests from a person/family/org — they belong
            // on the dedicated Mass Schedule page, not here. This used to
            // check `mass_schedule_id` (only set for Masses generated from
            // the recurring weekly template), which incorrectly let
            // admin-created special Masses like Simbang Gabi — which have
            // no mass_schedule_id — leak into this list. Checking `type`
            // directly covers both regular AND special Masses. Opt back
            // in with ?show_regular_masses=1 (name kept for the existing
            // query param / checkbox).
            ->when(! $showRegularMasses, fn ($q) => $q->where('type', '!=', 'mass'))
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
            'filters' => $request->only(['type', 'status', 'search']),
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

        // Reservation Created Date & Time is never entered by the
        // administrator — it's Laravel's own created_at timestamp, set the
        // instant Reservation::create() below runs. We only need to record
        // *who* created it.
        $data['created_by'] = $request->user()?->id;

        // The Church Availability & Conflict Detection Engine already
        // blocked this submission in StoreReservationRequest if it
        // collided with something and wasn't explicitly overridden. If we
        // got here with override_conflict=true, record who overrode it and
        // why, both on the reservation and in the audit log.
        if ($request->boolean('override_conflict')) {
            $data['conflict_overridden'] = true;
            $data['override_reason'] = $request->input('override_reason');
            $data['overridden_by'] = $request->user()?->id;
            $data['overridden_at'] = now();
        }

        $reservation = Reservation::create($data);

        AuditLogger::reservationCreated($reservation);

        if ($request->boolean('override_conflict')) {
            AuditLogger::conflictOverridden($reservation, (string) $request->input('override_reason'));
        }

        $this->seedRequirements($reservation);

        // Wedding Date selected -> automatically suggest Canonical
        // Interview / Pre-Cana / Marriage Banns / Rehearsal dates. Purely
        // a starting point: every field stays fully editable, and nothing
        // here blocks or delays creating the reservation itself — see
        // MarriagePreparationSchedulingService.
        if ($reservation->type === 'wedding' && $reservation->event_date) {
            app(MarriagePreparationSchedulingService::class)->generate($reservation);
        }

        $this->notifier->notifyAdmins(
            kind: 'new_reservation',
            title: 'New reservation created',
            body: "{$reservation->display_name}'s ".str_replace('_', ' ', $reservation->type)." on {$reservation->event_date->format('M j')} needs review.",
            reservation: $reservation,
            except: $request->user()
        );

        return redirect()->route('reservations.index')
            ->with('success', 'Reservation created.');
    }

    public function show(Request $request, Reservation $reservation): Response
    {
        $reservation->load('priest', 'linkedMass.priest', 'location', 'requirements', 'rotaAssignments', 'creator', 'updater', 'seminar');

        return Inertia::render('Reservations/Show', [
            'reservation' => $reservation,
            'priests' => Priest::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            // Where the admin came from (Archives vs Reservations list), including
            // that list's search/filter/pagination state, so "Back to List" can
            // return them to the exact page they were on instead of always
            // bouncing to /reservations. Only a same-site relative path is ever
            // trusted here — see isSafeReturnUrl().
            'from' => $this->safeReturnUrl($request->query('from')),
        ]);
    }

    /**
     * Only allow relative, same-site paths (e.g. "/archives?search=Tan") to be
     * used as a return URL. Rejects absolute/external URLs so this can't be
     * abused as an open redirect.
     */
    protected function safeReturnUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        // Must start with a single "/" (relative path) and not "//" (protocol-relative, i.e. external).
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        return $url;
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

    public function edit(Reservation $reservation): Response|RedirectResponse
    {
        // Completed/archived sacramental records are locked from normal
        // editing — Correct Record (see correct() below) is the only
        // sanctioned way to change one afterward. Enforced here too, not
        // just by hiding the Edit button, in case someone hits this route
        // directly.
        if ($reservation->is_locked) {
            return redirect()->route('reservations.show', $reservation)
                ->with('error', 'This is a completed sacramental record and is read-only. Use "Correct Record" to make an audited correction instead.');
        }

        return Inertia::render('Reservations/Edit', [
            'reservation' => $reservation,
            'priests' => Priest::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'chapels' => $this->chapels,
        ]);
    }

    public function update(StoreReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        // Same lock as edit() above — belt-and-suspenders in case the
        // normal edit form is submitted directly against a record that
        // became locked (e.g. marked Completed) after the form loaded.
        if ($reservation->is_locked) {
            return redirect()->route('reservations.show', $reservation)
                ->with('error', 'This is a completed sacramental record and is read-only. Use "Correct Record" to make an audited correction instead.');
        }

        $data = $request->validated();
        $data['details'] = $this->cleanDetails($data['type'], $data['details'] ?? []);

        // Reservation Updated Date & Time is Laravel's own updated_at
        // timestamp, set automatically by $reservation->update() below —
        // we only need to record *who* made the change.
        $data['updated_by'] = $request->user()?->id;

        if ($request->boolean('override_conflict')) {
            $data['conflict_overridden'] = true;
            $data['override_reason'] = $request->input('override_reason');
            $data['overridden_by'] = $request->user()?->id;
            $data['overridden_at'] = now();
        }

        // Detect an actual Wedding Date change BEFORE saving, so we know
        // whether to recalculate suggestions afterward.
        $eventDateChanged = $reservation->type === 'wedding'
            && array_key_exists('event_date', $data)
            && (string) $reservation->getOriginal('event_date') !== (string) $data['event_date'];

        $reservation->update($data);

        // Re-attaching (or confirming) a Pamisa sa Kalag's Mass link
        // clears any prior "Needs Review" flag — the admin has just acted
        // on it, whether by accepting a fresh suggestion or manually
        // picking another available Mass.
        if ($reservation->type === 'pamisa_sa_kalag' && $reservation->linked_mass_reservation_id) {
            app(\App\Services\PamisaMassLinkService::class)->clearReview($reservation);
        }

        // Wedding Date changed -> refresh only the SUGGESTED (not
        // manually-adjusted) activities to match the new date.
        // schedule_source = 'manual' items are left completely alone —
        // see requirement #4 / MarriagePreparationSchedulingService.
        $prepReviewNotice = null;

        if ($eventDateChanged) {
            app(MarriagePreparationSchedulingService::class)->generate($reservation, overwriteManual: false);

            // Requirement #5 — a manually adjusted schedule is never
            // silently overwritten by a Wedding Date change; instead the
            // admin is told to review it (and can use "Regenerate
            // Suggested Schedule" if they want it recalculated).
            $reservation->loadMissing('requirements', 'seminar');
            $hasManualPrep = $reservation->requirements
                ->whereIn('key', ['canonical_interview', 'marriage_banns', 'wedding_rehearsal'])
                ->contains(fn ($r) => $r->schedule_source === 'manual');
            $hasManualSeminar = $reservation->seminar?->schedule_source === 'manual';

            if ($hasManualPrep || $hasManualSeminar) {
                $prepReviewNotice = 'Wedding date changed. Some preparation schedules may need to be reviewed.';
            }
        }

        AuditLogger::reservationUpdated($reservation);

        if ($request->boolean('override_conflict')) {
            AuditLogger::conflictOverridden($reservation, (string) $request->input('override_reason'));
        }

        $redirect = redirect()->route('reservations.index')
            ->with('success', 'Reservation updated.');

        return $prepReviewNotice ? $redirect->with('warning', $prepReviewNotice) : $redirect;
    }

    /**
     * Controlled Correction: the ONLY sanctioned way to change a
     * completed/archived sacramental record. Deliberately separate from
     * update() above — no status changes, no scheduling-conflict engine,
     * no silent overwrite. Every changed field (contact info, schedule, or
     * anything inside `details`, however deeply nested — a single child's
     * name inside a group baptism's roster, for instance) gets its own
     * audit_logs row recording the previous value, the new value, who made
     * the change, when, and the mandatory reason — before the reservation
     * row itself is updated to the corrected values. Certificates and the
     * Archive always reflect the corrected data; the audit trail is where
     * the original value is preserved.
     */
    public function correct(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('correct', $reservation);

        abort_unless($reservation->is_locked, 403, 'Correct Record is only available for completed or archived reservations. Use the normal Edit action instead.');

        $validated = $request->validate([
            'correction_reason' => ['required', 'string', 'max:500'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_mobile' => ['required', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'event_date' => ['required', 'date'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'priest_id' => ['nullable', 'exists:priests,id'],
            'details' => ['nullable', 'array'],
        ]);

        $reason = $validated['correction_reason'];

        // Top-level scalar fields.
        $trackedFields = ['contact_name', 'contact_mobile', 'contact_email', 'contact_address', 'event_date', 'event_time', 'priest_id'];

        foreach ($trackedFields as $field) {
            $previous = $field === 'event_date'
                ? $reservation->event_date?->format('Y-m-d')
                : $reservation->getOriginal($field);
            $new = $validated[$field] ?? null;

            if ((string) $previous !== (string) $new) {
                AuditLogger::fieldCorrected($reservation, $field, $previous, $new, $reason);
            }
        }

        // Every scalar leaf inside `details`, however deeply nested —
        // covers everything from a single-child baptism's child_name to
        // one name inside a group baptism's roster or Pamisa sa Kalag's
        // list of the deceased.
        $previousDetails = Reservation::flattenDetails($reservation->details ?? []);
        $newDetails = Reservation::flattenDetails($validated['details'] ?? $reservation->details ?? []);

        foreach (array_unique(array_merge(array_keys($previousDetails), array_keys($newDetails))) as $path) {
            $previous = $previousDetails[$path] ?? null;
            $new = $newDetails[$path] ?? null;

            if ((string) $previous !== (string) $new) {
                AuditLogger::fieldCorrected($reservation, "details.{$path}", $previous, $new, $reason);
            }
        }

        // Status is deliberately untouched — a correction fixes data, it
        // never reopens or re-files the record.
        $reservation->update([
            'contact_name' => $validated['contact_name'],
            'contact_mobile' => $validated['contact_mobile'],
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_address' => $validated['contact_address'] ?? null,
            'event_date' => $validated['event_date'],
            'event_time' => $validated['event_time'] ?? null,
            'priest_id' => $validated['priest_id'] ?? null,
            'details' => $validated['details'] ?? $reservation->details,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()->route('reservations.show', $reservation)
            ->with('success', 'Correction saved. The change has been recorded in the audit history.');
    }

    public function destroy(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('delete', $reservation);

        // A completed (or already archived) reservation is a parish
        // record — it's what a certificate or the Archives page is
        // generated from. Deleting it would silently break "find the
        // baptism/wedding record to print a certificate" later, so it
        // can only be filed away (Archive), never hard-deleted. Only
        // draft/confirmed reservations that haven't happened yet — a
        // genuine booking mistake — are deletable.
        if (in_array($reservation->status, ['completed', 'archived'], true)) {
            return redirect()->route('reservations.show', $reservation)
                ->with('error', 'Completed reservations can\'t be deleted — they\'re the record a certificate is generated from. Use Archive instead if it needs to be filed away.');
        }

        $this->notifier->notifyAdmins(
            kind: 'cancelled',
            title: 'Reservation cancelled',
            body: "{$reservation->display_name}'s ".str_replace('_', ' ', $reservation->type)." on {$reservation->event_date->format('M j')} was cancelled.",
            except: $request->user()
        );

        AuditLogger::reservationCancelled($reservation);

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
            // `status` is the source of truth going forward; `is_completed`
            // is still accepted (and kept working) for any caller that
            // hasn't moved to sending status yet — see the model's
            // saving() hook, which derives is_completed from status
            // whenever status is present and dirty.
            'items.*.status' => ['sometimes', 'string', Rule::in(\App\Models\ReservationRequirement::STATUSES)],
            'items.*.is_completed' => ['sometimes', 'boolean'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
            'items.*.date_started' => ['nullable', 'date'],
            'items.*.date_completed' => ['nullable', 'date'],
            'items.*.meta' => ['nullable', 'array'],
        ]);

        $schedulingService = app(MarriagePreparationSchedulingService::class);
        $rules = config('marriage_preparation_rules');

        foreach ($validated['items'] as $item) {
            $requirement = $reservation->requirements->firstWhere('id', $item['id'])
                ?? $reservation->requirements()->find($item['id']);

            $update = ['note' => $item['note'] ?? null];

            foreach (['date_started', 'date_completed', 'meta'] as $optionalField) {
                if (array_key_exists($optionalField, $item)) {
                    $update[$optionalField] = $item[$optionalField];
                }
            }

            // Canonical Interview, Marriage Banns, and Wedding Rehearsal
            // carry their suggested dates through this same endpoint (see
            // WeddingRequirementsPanel.vue). The moment an admin submits a
            // change to one of those date-bearing fields, flip it to
            // 'manual' so it's never silently overwritten by a later
            // Wedding Date edit or schedule regeneration — see
            // MarriagePreparationSchedulingService::generate().
            if (array_key_exists('date_started', $item) || array_key_exists('date_completed', $item) || array_key_exists('meta', $item)) {
                $update['schedule_source'] = 'manual';
            }

            // Requirement #7 — a marriage-preparation activity must occur
            // before the Wedding Date. Requirement #8 — reuse the
            // existing conflict-detection engine for venue/time collisions.
            if ($requirement && $reservation->type === 'wedding') {
                if ($requirement->key === 'canonical_interview' && ! empty($update['meta']['interview_date'])) {
                    if ($error = $schedulingService->validateBeforeWedding($reservation, 'Canonical Interview', $update['meta']['interview_date'])) {
                        return back()->withErrors(['schedule' => $error])->withInput();
                    }
                    if (! empty($update['meta']['interview_time']) && ! empty($update['meta']['venue'])) {
                        $conflict = $this->conflicts->findPrepActivityConflict(
                            'canonical_interview', 'interview_date', 'interview_time',
                            $rules['canonical_interview']['duration_minutes'],
                            $update['meta']['venue'], $update['meta']['interview_date'], $update['meta']['interview_time'],
                            $requirement->id
                        );
                        if ($conflict) {
                            return back()->withErrors(['schedule' => "{$update['meta']['venue']} is already booked for another Canonical Interview at that time."])->withInput();
                        }
                    }
                    // A manually confirmed date is, by definition, Scheduled.
                    $update['meta']['status'] = 'scheduled';
                }

                if ($requirement->key === 'wedding_rehearsal' && ! empty($update['meta']['rehearsal_date'])) {
                    if ($error = $schedulingService->validateBeforeWedding($reservation, 'Wedding Rehearsal', $update['meta']['rehearsal_date'])) {
                        return back()->withErrors(['schedule' => $error])->withInput();
                    }
                    if (! empty($update['meta']['rehearsal_time']) && ! empty($update['meta']['venue'])) {
                        // Requirement: "Start Time" + "End Time" (or a
                        // duration) are both editable in the Adjust
                        // Schedule modal — derive the minutes actually
                        // used for conflict-checking and storage from
                        // whichever the admin supplied, falling back to
                        // the configured default (60 min).
                        $defaultDuration = (int) $rules['wedding_rehearsal']['duration_minutes'];
                        $durationMinutes = $defaultDuration;

                        if (! empty($update['meta']['rehearsal_end_time'])) {
                            $start = Carbon::parse($update['meta']['rehearsal_time']);
                            $end = Carbon::parse($update['meta']['rehearsal_end_time']);
                            $durationMinutes = $end->gt($start) ? $start->diffInMinutes($end) : $defaultDuration;
                        } elseif (! empty($update['meta']['duration_minutes'])) {
                            $durationMinutes = (int) $update['meta']['duration_minutes'];
                        }

                        // Requirement #6/#3 — re-run FULL conflict detection
                        // (Main Church venue AND assigned priest) the moment
                        // the admin adjusts the suggested rehearsal, not
                        // just a venue-only check. Blocks the save on
                        // either kind of collision.
                        $conflict = $this->conflicts->findRehearsalSlotConflict(
                            $update['meta']['rehearsal_date'],
                            $update['meta']['rehearsal_time'],
                            $durationMinutes,
                            $update['meta']['venue'],
                            $reservation->location_id,
                            $reservation->priest_id,
                            $requirement->id,
                            $reservation->id
                        );
                        if ($conflict) {
                            return back()->withErrors(['schedule' => $conflict])->withInput();
                        }

                        // A manually confirmed date/time is, by definition,
                        // Scheduled — not just a suggestion anymore. Also
                        // fill in the derived end time/duration so the
                        // stored meta always has both, regardless of which
                        // one the admin actually typed.
                        $update['meta']['rehearsal_end_time'] = $update['meta']['rehearsal_end_time']
                            ?? Carbon::parse($update['meta']['rehearsal_time'])->addMinutes($durationMinutes)->format('H:i');
                        $update['meta']['duration_minutes'] = $durationMinutes;
                        $update['meta']['status'] = 'scheduled';
                    }
                }

                if ($requirement->key === 'marriage_banns' && ! empty($update['meta']['banns_date_3'])) {
                    $b1 = $update['meta']['banns_date_1'] ?? null;
                    $b2 = $update['meta']['banns_date_2'] ?? null;
                    $b3 = $update['meta']['banns_date_3'];

                    // Requirement #4 — the three announcement dates must be
                    // in chronological order.
                    if ($b1 && $b2 && ! (Carbon::parse($b1)->lt(Carbon::parse($b2)) && Carbon::parse($b2)->lt(Carbon::parse($b3)))) {
                        return back()->withErrors(['schedule' => 'The three Marriage Banns announcement dates must be in chronological order.'])->withInput();
                    }

                    if ($error = $schedulingService->validateBeforeWedding($reservation, 'Marriage Banns', $b3)) {
                        return back()->withErrors(['schedule' => $error])->withInput();
                    }

                    // A manually confirmed set of dates is, by definition,
                    // Scheduled — and keep the legacy range columns in sync.
                    $update['meta']['status'] = 'scheduled';
                    $update['date_started'] = $b1;
                    $update['date_completed'] = $b3;
                }
            }

            if (array_key_exists('status', $item)) {
                $update['status'] = $item['status'];
            } elseif (array_key_exists('is_completed', $item)) {
                $update['is_completed'] = $item['is_completed'];
            }

            $reservation->requirements()->where('id', $item['id'])->update($update);
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

        $this->authorize('updateStatus', [$reservation, $validated['status']]);

        $completedLockBlocker = $this->completedLockBlocker($reservation, $validated['status']);

        if ($completedLockBlocker) {
            return back()->withErrors(['status' => $completedLockBlocker]);
        }

        $futureDateBlocker = $this->futureDateBlocker($reservation, $validated['status']);

        if ($futureDateBlocker) {
            return back()->withErrors(['status' => $futureDateBlocker]);
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
            'updated_by' => $request->user()?->id,
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

        $this->authorize('updateStatus', [$reservation, $validated['status']]);

        $newPriestId = $validated['priest_id'] ?? null;

        // Priest reassignment must ALWAYS be checked for a conflict, not
        // only when the status is also transitioning into 'confirmed'.
        // confirmationBlocker() below skips its own checks entirely once
        // a reservation is already confirmed (nothing about its status is
        // changing), which used to mean reassigning the priest on an
        // already-confirmed reservation right here bypassed conflict
        // detection completely — a priest could silently be double-booked.
        // This runs unconditionally, before the mutation, so it always
        // sees the OLD priest_id via $reservation->id as the exclusion key
        // and the NEW priest_id as the one being checked.
        $priestBlocker = $this->priestReassignmentBlocker($reservation, $newPriestId);

        if ($priestBlocker) {
            return back()->withErrors(['priest_id' => $priestBlocker]);
        }

        // Apply the priest reassignment before running confirm-time conflict
        // checks, so "assign priest + confirm" in one click is checked
        // against the priest that's about to be saved, not the old one.
        $reservation->priest_id = $newPriestId;

        $completedLockBlocker = $this->completedLockBlocker($reservation, $validated['status']);

        if ($completedLockBlocker) {
            return back()->withErrors(['status' => $completedLockBlocker]);
        }

        $futureDateBlocker = $this->futureDateBlocker($reservation, $validated['status']);

        if ($futureDateBlocker) {
            return back()->withErrors(['status' => $futureDateBlocker]);
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
        $reservation->updated_by = $request->user()?->id;

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
     * A reservation can't be marked Completed until its event date has
     * actually happened — a sacrament scheduled weeks or months out isn't
     * "done" yet just because someone updated its status. Only checks the
     * date (not the time), so a reservation becomes eligible starting the
     * same calendar day its event is scheduled.
     */
    protected function futureDateBlocker(Reservation $reservation, string $newStatus): ?string
    {
        if ($newStatus !== 'completed' || ! $reservation->event_date) {
            return null;
        }

        if ($reservation->event_date->startOfDay()->gt(now()->startOfDay())) {
            return 'Cannot mark this reservation as Completed — its event date ('.$reservation->event_date->format('M j, Y').') hasn\'t happened yet.';
        }

        return null;
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

        // Pamisa sa Kalag reservations intentionally piggyback on an
        // existing Mass slot (see SchedulingConflictService::sharesMassSlot).
        // linked_mass_reservation_id lives on its own column, not inside
        // `details`, so fold it in here for the conflict checks below.
        $conflictDetails = array_merge(
            $reservation->details ?? [],
            ['linked_mass_reservation_id' => $reservation->linked_mass_reservation_id]
        );

        if ($reservation->priest_id) {
            $conflict = $this->conflicts->findPriestConflict(
                $reservation->priest_id,
                $reservation->event_date->format('Y-m-d'),
                substr((string) $reservation->event_time, 0, 5),
                $reservation->type,
                $reservation->id,
                $conflictDetails
            );

            if ($conflict) {
                $priestName = $reservation->priest?->name ?? 'This priest';

                return $this->conflicts->formatPriestConflictMessage($priestName, $conflict);
            }
        }

        $chapel = $reservation->details['chapel'] ?? null;

        if ($reservation->type === 'chapel_mass' && $chapel) {
            $conflict = $this->conflicts->findChapelConflict(
                $chapel,
                $reservation->event_date->format('Y-m-d'),
                substr((string) $reservation->event_time, 0, 5),
                $reservation->type,
                $reservation->id,
                $conflictDetails
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
                $reservation->id,
                $conflictDetails
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
     * Centralized priest double-booking guard for a reassignment — used
     * specifically by updateActions() above to close the gap where
     * confirmationBlocker() skips ALL of its checks once a reservation is
     * already confirmed (it only cares about a STATUS transition, not a
     * priest change). Runs regardless of the reservation's current or
     * incoming status: a priest genuinely cannot be double-booked, whether
     * the reservation holding one side of the conflict is a draft or
     * already confirmed.
     *
     * Deliberately mirrors confirmationBlocker()'s own priest check
     * (same SchedulingConflictService::findPriestConflict call, same
     * shares-a-Mass-slot exemption for Pamisa sa Kalag via
     * linked_mass_reservation_id) so the two never disagree about what
     * counts as a conflict — only WHEN they run differs.
     */
    protected function priestReassignmentBlocker(Reservation $reservation, ?int $newPriestId): ?string
    {
        // Unassigning a priest, or "reassigning" to the same priest it
        // already has, can never create a new conflict.
        if (! $newPriestId || $newPriestId === $reservation->priest_id) {
            return null;
        }

        if (! $reservation->event_date || ! $reservation->event_time) {
            return null;
        }

        $conflictDetails = array_merge(
            $reservation->details ?? [],
            ['linked_mass_reservation_id' => $reservation->linked_mass_reservation_id]
        );

        $conflict = $this->conflicts->findPriestConflict(
            $newPriestId,
            $reservation->event_date->format('Y-m-d'),
            substr((string) $reservation->event_time, 0, 5),
            $reservation->type,
            $reservation->id,
            $conflictDetails
        );

        if (! $conflict) {
            return null;
        }

        $priestName = Priest::find($newPriestId)?->name ?? 'This priest';

        return $this->conflicts->formatPriestConflictMessage($priestName, $conflict);
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
            body: "{$reservation->display_name}'s ".str_replace('_', ' ', $reservation->type)." on {$reservation->event_date->format('M j')} is now confirmed.",
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
     * Returns the list of "HH:MM" slots already taken by a reservation
     * that still legitimately holds its slot — draft, confirmed, or
     * completed (see SchedulingConflictService::BLOCKING_STATUSES; an
     * archived reservation is this app's cancel state, so it never holds
     * a slot) — for that priest on that date, so the create/edit form can
     * grey them out before the user submits. `exclude` lets the edit form
     * ignore the reservation currently being edited. When `chapel` is
     * also supplied (Chapel Mass bookings), a second list of slots
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
                ->whereIn('status', SchedulingConflictService::BLOCKING_STATUSES)
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
                ->whereIn('status', SchedulingConflictService::BLOCKING_STATUSES)
                ->whereDate('event_date', $validated['date'])
                ->whereNotNull('event_time')
                ->when($validated['exclude'] ?? null, fn ($q, $excludeId) => $q->where('id', '!=', $excludeId))
                ->get()
                ->map(fn (Reservation $r) => substr((string) $r->event_time, 0, 5))
                ->values();
        }

        // Wedding, Baptism, Burial, First Communion, and Confirmation all
        // share the single Main Sanctuary venue (config
        // `church_schedule.main_sanctuary_types` — the same list
        // StoreReservationRequest and ChurchAvailabilityService use), so
        // any reservation of these types that still legitimately holds
        // its slot (draft, confirmed, or completed — see
        // SchedulingConflictService::BLOCKING_STATUSES) on the same date
        // blocks a slot for the others too — same idea as the per-priest
        // / per-chapel checks above.
        $takenVenue = collect();
        $mainSanctuaryTypes = config('church_schedule.main_sanctuary_types', ['wedding', 'baptism', 'burial']);

        if (in_array($validated['type'] ?? null, $mainSanctuaryTypes, true)) {
            $mainSanctuaryId = Location::where('name', config('church_schedule.main_sanctuary_name', 'Parish of the Holy Sacraments'))->value('id');

            if ($mainSanctuaryId) {
                $takenVenue = Reservation::query()
                    ->where('location_id', $mainSanctuaryId)
                    ->whereIn('type', $mainSanctuaryTypes)
                    ->whereIn('status', SchedulingConflictService::BLOCKING_STATUSES)
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
     * GET /reservations/mass-schedules?date=YYYY-MM-DD[&exclude=ID]
     *
     * The REAL, individually-editable Mass occurrences for a given date —
     * `reservations` rows with type = 'mass' (regular, generated from the
     * weekly template, or special/one-off) — not the weekly template
     * itself. This is what Pamisa sa Kalag attaches to: the Main Church is
     * fixed as the location, and the reservation is scheduled WITHIN one
     * of these existing Mass occurrences rather than getting an
     * independent church-venue schedule of its own.
     *
     * A Mass that's cancelled, or already at capacity for Pamisa sa Kalag
     * intentions (config('mass_schedule.max_pamisa_intentions_per_mass')),
     * is never offered — the admin can never select a time that doesn't
     * correspond to an existing, available Mass Schedule occurrence.
     *
     * One Mass is marked `suggested` (the earliest available occurrence
     * for the date) so the UI can present a single 💡 SUGGESTED option up
     * front, with the rest available via "Adjust Schedule".
     *
     * `exclude` (optional) is the current Pamisa sa Kalag reservation's own
     * id, when editing one that's already linked to a Mass — so that
     * Mass's own intention count doesn't count itself against its capacity.
     */
    public function massSchedules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'exclude' => ['nullable', 'integer'],
        ]);

        $capacity = (int) config('mass_schedule.max_pamisa_intentions_per_mass', 10);
        $mainSanctuaryName = config('church_schedule.main_sanctuary_name', 'Parish of the Holy Sacraments');

        $masses = Reservation::query()
            ->where('type', 'mass')
            ->where('status', 'confirmed')
            ->whereDate('event_date', $validated['date'])
            ->with(['priest:id,name', 'massSchedule:id,label'])
            ->orderBy('event_time')
            ->get();

        $schedules = $masses
            ->map(function (Reservation $mass) use ($capacity, $validated, $mainSanctuaryName) {
                $intentionCount = $mass->pamisaIntentions()
                    ->where('status', '!=', 'cancelled')
                    ->when($validated['exclude'] ?? null, fn ($q, $excludeId) => $q->where('id', '!=', $excludeId))
                    ->count();

                return [
                    'id' => $mass->id,
                    'date' => $mass->event_date->format('Y-m-d'),
                    'start_time' => substr((string) $mass->event_time, 0, 5),
                    'end_time' => $this->massEndTime($mass),
                    'mass_type' => $mass->title ?: ($mass->massSchedule?->label ?: 'Mass'),
                    'label' => $this->formatMassOccurrenceLabel($mass),
                    'priest_id' => $mass->priest_id,
                    'priest_name' => $mass->priest?->name,
                    'venue' => $mainSanctuaryName,
                    'intention_count' => $intentionCount,
                    'capacity' => $capacity,
                    'is_full' => $intentionCount >= $capacity,
                ];
            })
            // A full Mass is never offered as an available option — Pamisa
            // sa Kalag can never select a time that doesn't correspond to
            // an existing, AVAILABLE Mass Schedule occurrence.
            ->reject(fn (array $s) => $s['is_full'])
            ->values();

        // The earliest available occurrence is the automatic suggestion —
        // marked here (server-side) so the UI never has to guess.
        $suggestedId = $schedules->first()['id'] ?? null;
        $schedules = $schedules->map(fn (array $s) => $s + ['suggested' => $s['id'] === $suggestedId]);

        return response()->json(['schedules' => $schedules->values()]);
    }

    protected function massEndTime(Reservation $mass): ?string
    {
        if (! $mass->event_time) {
            return null;
        }

        $minutes = $this->availabilityEngine->durationMinutes('mass', $mass->details ?? []);

        return Carbon::parse($mass->event_time)->addMinutes($minutes)->format('H:i');
    }

    protected function formatMassOccurrenceLabel(Reservation $mass): string
    {
        $time = $mass->event_time ? Carbon::parse($mass->event_time)->format('g:i A') : '—';
        $type = $mass->title ?: ($mass->massSchedule?->label ?: 'Mass');
        $priest = $mass->priest?->name;

        return trim("{$time} — {$type}".($priest ? " — {$priest}" : ' — Unassigned'));
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
                    $reservation->requirements()->create($this->requirementAttributes($item, [
                        'child_index' => $index,
                        'child_name' => $child['child_name'] ?? "Child ".($index + 1),
                    ]));
                }
            }

            return;
        }

        foreach ($items as $item) {
            $reservation->requirements()->create($this->requirementAttributes($item));
        }
    }

    /**
     * Map one config/reservation_requirements.php checklist entry into the
     * attributes for a new ReservationRequirement row. Pulls every field
     * the config can define (status/is_required/group_key/group_label/
     * description) rather than just key/label — omitting these left new
     * wedding reservations with `group_key = null`, which is why the
     * Documents/Marriage Preparation sections and their "X of Y" counters
     * showed nothing for freshly-created weddings (the panel filters rows
     * by `group_key`).
     */
    protected function requirementAttributes(array $item, array $overrides = []): array
    {
        return array_merge([
            'key' => $item['key'],
            'label' => $item['label'],
            'description' => $item['description'] ?? null,
            'is_completed' => false,
            'status' => 'pending',
            'is_required' => $item['is_required'] ?? true,
            'group_key' => $item['group_key'] ?? null,
            'group_label' => $item['group_label'] ?? null,
        ], $overrides);
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