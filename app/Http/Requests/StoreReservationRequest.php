<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Models\Priest;
use App\Services\AuditLogger;
use App\Services\ChurchAvailabilityService;
use App\Services\SchedulingConflictService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    /**
     * Wedding, Baptism, Burial, First Communion, Confirmation, and Pamisa
     * sa Kalag only ever happen at the parish's Main Sanctuary — there's
     * no venue picker for these types in the form, so we assign it here
     * rather than relying on the UI. This makes the existing venue-conflict
     * check (findLocationConflict, already used for confirm-time and
     * priest-style double-booking prevention) apply to these types
     * automatically, the same way the priest conflict check already does.
     * Mirrors config('church_schedule.main_sanctuary_types') — the single
     * source of truth ChurchAvailabilityService::resolveVenue() also reads
     * from, so both engines agree on which types default to the Main
     * Sanctuary. (Pamisa sa Kalag still doesn't participate in the
     * conflict engine itself — see config('church_schedule.occupying_types')
     * — this assignment is only so it displays the same read-only Main
     * Church location as the others.)
     */
    protected function mainSanctuaryTypes(): array
    {
        return config('church_schedule.main_sanctuary_types', ['wedding', 'baptism', 'burial']);
    }

    /**
     * Anyone authenticated can submit this form. The one exception:
     * override_conflict=1 is an admin-only escape hatch (it lets a
     * detected schedule conflict through anyway) — see
     * App\Policies\ReservationPolicy::overrideConflict(). A non-admin
     * submitting override_conflict=1 is rejected here, at the door,
     * rather than silently ignored (which would leave them confused
     * about why their conflicting time still got rejected).
     */
    public function authorize(): bool
    {
        if ($this->boolean('override_conflict')) {
            return $this->user()?->can('overrideConflict', \App\Models\Reservation::class) ?? false;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (in_array($this->input('type'), $this->mainSanctuaryTypes(), true) && !$this->input('location_id')) {
            $mainSanctuary = Location::where('name', config('church_schedule.main_sanctuary_name', 'Parish of the Holy Sacraments'))->first();

            if ($mainSanctuary) {
                $this->merge(['location_id' => $mainSanctuary->id]);
            }
        }

        // The Mass Schedule is the single source of truth for Pamisa sa
        // Kalag's date, time, and priest — there is no independent Event
        // Time field for it. Whatever the admin's client happened to send
        // for these is discarded and overwritten here from the linked
        // Mass occurrence itself, so the two can never disagree.
        if ($this->input('type') === 'pamisa_sa_kalag' && $this->input('linked_mass_reservation_id')) {
            $mass = \App\Models\Reservation::where('type', 'mass')
                ->find($this->input('linked_mass_reservation_id'));

            if ($mass) {
                $this->merge([
                    'event_date' => $mass->event_date->format('Y-m-d'),
                    'event_time' => $mass->event_time ? substr((string) $mass->event_time, 0, 5) : null,
                    'priest_id' => $mass->priest_id,
                ]);
            }
        }
    }

    public function rules(): array
    {
        $type = $this->input('type');

        return array_merge([
            // Global fields
            'type' => ['required', Rule::in([
                'wedding', 'baptism', 'burial', 'first_communion', 'confirmation',
                'pamisa_sa_kalag', 'school_mass', 'chapel_mass',
                'house_blessing', 'business_blessing', 'vehicle_blessing',
                'anointing_of_the_sick', 'spiritual_direction', 'special_intention',
                'others',
            ])],
            // Pamisa sa Kalag is a Mass intention / deceased-name list
            // entered directly by the admin, not a normal reservation with
            // a customer/contact profile — the Reservation Information
            // card is hidden for it in the form (ReservationForm.vue), so
            // don't require the fields it doesn't collect. Every other
            // type keeps them required exactly as before.
            'contact_name' => [$type === 'pamisa_sa_kalag' ? 'nullable' : 'required', 'string', 'max:255'],
            'contact_mobile' => [$type === 'pamisa_sa_kalag' ? 'nullable' : 'required', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            // Past dates are blocked only on create — editing an existing
            // reservation must not fail validation just because its own
            // saved date has since passed (e.g. marking it completed
            // after the fact), so this only applies when there's no
            // {reservation} route-bound model yet.
            'event_date' => array_filter([
                'required',
                'date',
                $this->route('reservation') ? null : 'after_or_equal:today',
            ]),
            'event_time' => ['nullable', 'date_format:H:i'],
            'priest_id' => ['nullable', 'exists:priests,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            // Only meaningful for pamisa_sa_kalag (see conditionalRules()
            // below, which makes it required for that type); nullable here
            // so every other type's payload validates fine without it.
            'linked_mass_reservation_id' => ['nullable', 'integer', Rule::exists('reservations', 'id')],
            'offering_amount' => ['nullable', 'numeric', 'min:0'],
            // Church Availability & Conflict Detection Engine override —
            // only meaningful when the engine actually found a conflict
            // (see checkChurchAvailability()); a reason is mandatory
            // whenever an override is being requested.
            'override_conflict' => ['nullable', 'boolean'],
            'override_reason' => ['required_if:override_conflict,1', 'nullable', 'string', 'max:500'],
        ], $this->conditionalRules($type));
    }

    /**
     * Beyond field-level rules, reject the whole submission if it would
     * double-book a priest or a chapel. This runs after the normal rules
     * pass, so we only bother checking once we know priest_id/chapel/
     * event_date/event_time are individually valid.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->checkChurchAvailability($validator);
            $this->checkSchedulingConflict($validator);
            $this->checkPamisaMassLink($validator);
        });
    }

    /**
     * Pamisa sa Kalag must attach to a real, currently-available Mass
     * occurrence — never an arbitrary time. Rejects the submission if the
     * chosen Mass no longer qualifies: it's not actually type = 'mass',
     * it's been cancelled, or it's already at capacity for Pamisa sa Kalag
     * intentions (config('mass_schedule.max_pamisa_intentions_per_mass')).
     * Runs the count check with a row lock so two admins can't both
     * squeeze into the last open slot on the same Mass at once.
     */
    protected function checkPamisaMassLink(Validator $validator): void
    {
        if ($this->input('type') !== 'pamisa_sa_kalag') {
            return;
        }

        $massId = $this->input('linked_mass_reservation_id');

        if (! $massId) {
            return; // already flagged as required by conditionalRules()
        }

        $mass = \App\Models\Reservation::where('id', $massId)->first();

        if (! $mass || $mass->type !== 'mass') {
            $validator->errors()->add('linked_mass_reservation_id', 'Select a valid Mass schedule for this date.');

            return;
        }

        if ($mass->status !== 'confirmed') {
            $validator->errors()->add('linked_mass_reservation_id', 'That Mass has been cancelled — please choose another available Mass schedule.');

            return;
        }

        $capacity = (int) config('mass_schedule.max_pamisa_intentions_per_mass', 10);
        $ownReservationId = $this->route('reservation')?->id;

        $intentionCount = \Illuminate\Support\Facades\DB::transaction(function () use ($massId, $ownReservationId) {
            return \App\Models\Reservation::where('linked_mass_reservation_id', $massId)
                ->where('status', '!=', 'cancelled')
                ->when($ownReservationId, fn ($q) => $q->where('id', '!=', $ownReservationId))
                ->lockForUpdate()
                ->count();
        });

        if ($intentionCount >= $capacity) {
            $validator->errors()->add('linked_mass_reservation_id', 'That Mass schedule is already full. Please choose another available Mass schedule.');
        }
    }

    /**
     * Church Availability & Conflict Detection Engine — the primary,
     * whole-church gate. Runs before the narrower priest/chapel-specific
     * checks below (which still run for their own, more specific error
     * messaging). Blocks the submission if:
     *   - the date falls inside an active BlockedDate period, or
     *   - the requested date/time/type collides with anything already
     *     occupying the single church venue (Mass, Wedding, Baptism,
     *     Burial, First Communion, Confirmation, School Mass, Chapel Mass,
     *     or another approved church event) — Pamisa sa Kalag is exempt,
     *     since it attaches to an existing Mass Schedule instead of
     *     reserving independent time.
     * An admin can bypass either block by submitting override_conflict=1
     * with an override_reason; ReservationController records that
     * override on the reservation and in the audit log. Every prevented
     * conflict (i.e. not overridden) is also logged.
     */
    protected function checkChurchAvailability(Validator $validator): void
    {
        $date = $this->input('event_date');
        $time = $this->input('event_time');
        $type = $this->input('type');

        if (! $date || ! $type || $type === 'pamisa_sa_kalag') {
            return;
        }

        $engine = app(ChurchAvailabilityService::class);
        $currentReservation = $this->route('reservation');
        $overriding = $this->boolean('override_conflict');
        $locationId = $this->input('location_id');
        // Must be the SAME details payload ChurchAvailabilityService uses
        // for duration (group baptism/batch First Communion/wedding
        // ceremony type variants — see ReservationDuration) and for venue
        // resolution (chapel_mass's details.chapel, school_mass's
        // details.venue). Without this, findConflict() below silently
        // fell back to flat default durations and, worse, could never
        // resolve chapel_mass/school_mass to a venue at all — so those
        // types never registered a conflict with anything.
        $details = (array) $this->input('details', []);

        $blocked = $engine->isBlocked($date, $locationId);

        if ($blocked && ! $overriding) {
            $validator->errors()->add(
                'event_date',
                "{$date} falls within a blocked period — \"{$blocked->title}\"".($blocked->reason ? " ({$blocked->reason})" : '').
                    '. An administrator can override this with a reason if the reservation must proceed.'
            );

            AuditLogger::conflictPrevented(
                "Blocked-date reservation attempt on {$date} during \"{$blocked->title}\" was prevented.",
                ['date' => $date, 'type' => $type, 'blocked_date_title' => $blocked->title]
            );
        }

        if (! $time || ! $engine->occupiesChurch($type)) {
            return;
        }

        $conflict = $engine->findConflict($date, $time, $type, $currentReservation?->id, $locationId, $details);

        if ($conflict && ! $overriding) {
            $conflictTime = $conflict['start']->format('g:i A').' – '.$conflict['end']->format('g:i A');

            $validator->errors()->add(
                'event_time',
                "This overlaps with {$conflict['label']} ({$conflictTime}). Choose a different time, or an administrator can override with a reason."
            );

            AuditLogger::conflictPrevented(
                "A {$type} reservation attempt on {$date} at {$time} was prevented — overlapped with {$conflict['label']} ({$conflictTime}).",
                ['date' => $date, 'time' => $time, 'type' => $type, 'conflicting_type' => $conflict['type']]
            );
        }
    }

    protected function checkSchedulingConflict(Validator $validator): void
    {
        $date = $this->input('event_date');
        $time = $this->input('event_time');
        $type = $this->input('type');

        if (! $date || ! $time) {
            return;
        }

        $service = app(SchedulingConflictService::class);
        $currentReservation = $this->route('reservation');
        $details = (array) $this->input('details', []);

        // linked_mass_reservation_id is its own top-level field, not part
        // of `details` — fold it in here so SchedulingConflictService's
        // sharesMassSlot() can recognize a Pamisa sa Kalag reservation as
        // legitimately sharing its priest/time with the Mass it's linked
        // to, instead of treating that Mass as a conflicting double-booking
        // of the very priest it copied its own priest_id from.
        if ($this->input('type') === 'pamisa_sa_kalag') {
            $details['linked_mass_reservation_id'] = $this->input('linked_mass_reservation_id');
        }

        $priestId = $this->input('priest_id');

        if ($priestId) {
            $conflict = $service->findPriestConflict(
                $priestId,
                $date,
                $time,
                $type,
                $currentReservation?->id,
                $details
            );

            if ($conflict) {
                $priestName = Priest::find($priestId)?->name ?? 'The priest';

                $validator->errors()->add(
                    'event_time',
                    $service->formatPriestConflictMessage($priestName, $conflict)
                );

                return;
            }
        }

        $locationId = $this->input('location_id');

        // Pamisa sa Kalag deliberately excluded — it always shares the
        // exact date/time of the existing Mass Schedule slot it attaches
        // to (see the Mass Schedule picker in ReservationForm.vue), so a
        // same-location check here would always "conflict" with the very
        // Mass it's riding on. It got a location_id above purely for
        // read-only display (see mainSanctuaryTypes() docblock) — it was
        // never meant to reserve independent time at that location, which
        // is exactly what checkChurchAvailability() already assumes by
        // skipping this type entirely.
        if ($locationId && $type !== 'pamisa_sa_kalag') {
            $conflict = $service->findLocationConflict(
                $locationId,
                $date,
                $time,
                $type,
                $currentReservation?->id,
                $details
            );

            if ($conflict) {
                $locationName = Location::find($locationId)?->name ?? 'That venue';
                $conflictTime = Carbon::parse($conflict->event_time)->format('g:i A');
                $conflictDate = $conflict->event_date->format('F j, Y');

                $validator->errors()->add(
                    'location_id',
                    "{$locationName} is already booked for a confirmed reservation at {$conflictTime} on {$conflictDate}."
                );

                return;
            }
        }

        $chapel = $this->input('details.chapel');

        if ($type === 'chapel_mass' && $chapel) {
            $conflict = $service->findChapelConflict(
                $chapel,
                $date,
                $time,
                $type,
                $currentReservation?->id,
                $details
            );

            if ($conflict) {
                $conflictTime = Carbon::parse($conflict->event_time)->format('g:i A');
                $conflictDate = $conflict->event_date->format('F j, Y');

                $validator->errors()->add(
                    'event_time',
                    "{$chapel} already has a confirmed Mass at {$conflictTime} on {$conflictDate}."
                );
            }
        }
    }

    protected function conditionalRules(?string $type): array
    {
        return match ($type) {
            'wedding' => [
                'details.groom_name' => ['required', 'string', 'max:255'],
                'details.bride_name' => ['required', 'string', 'max:255'],
                'details.ceremony_type' => ['required', Rule::in(['nuptial_mass', 'liturgy_of_the_word'])],
                'details.canonical_interview' => ['boolean'],
                'details.marriage_banns' => ['boolean'],
                // Wedding Rehearsal is intentionally NOT validated here.
                // Its one source of truth is the wedding_rehearsal
                // ReservationRequirement (meta.rehearsal_date/time/venue/
                // facilitator), managed via
                // reservations.requirements.update and the automatic
                // suggestion engine — see
                // MarriagePreparationSchedulingService. A details.rehearsal_date
                // field was removed from the Wedding Details form to avoid
                // two schedules that could drift out of sync.
            ],
            'baptism' => $this->input('details.baptism_type') === 'group' ? [
                'details.baptism_type' => ['required', Rule::in(['individual', 'group'])],
                'details.children' => ['required', 'array', 'min:1'],
                'details.children.*.child_name' => ['required', 'string', 'max:255'],
                'details.children.*.father_name' => ['required', 'string', 'max:255'],
                'details.children.*.mother_maiden_name' => ['required', 'string', 'max:255'],
                'details.children.*.godparents' => ['nullable', 'array'],
                'details.children.*.godparents.*.name' => ['required_with:details.children.*.godparents', 'string', 'max:255'],
            ] : [
                'details.child_name' => ['required', 'string', 'max:255'],
                'details.father_name' => ['required', 'string', 'max:255'],
                'details.mother_maiden_name' => ['required', 'string', 'max:255'],
                'details.baptism_type' => ['required', Rule::in(['individual', 'group'])],
                'details.godparents' => ['nullable', 'array'],
                'details.godparents.*.name' => ['required_with:details.godparents', 'string', 'max:255'],
            ],
            'burial' => [
                'details.deceased_name' => ['required', 'string', 'max:255'],
                'details.age' => ['nullable', 'integer', 'min:0', 'max:150'],
                'details.cause_of_death' => ['nullable', 'string', 'max:255'],
                'details.service_type' => ['required', Rule::in(['funeral_mass'])],
                'details.cemetery' => ['nullable', 'string', 'max:255'],
            ],
            'pamisa_sa_kalag' => [
                'details.names' => ['required', 'string'],
                // The specific existing Mass occurrence (reservations.id,
                // type = 'mass') this Pamisa sa Kalag reservation attaches
                // to. This is the ONLY thing that determines the schedule
                // — see prepareForValidation(), which copies the Mass's
                // own date/time/priest onto this reservation, and
                // checkPamisaMassLink() below, which rejects a Mass that's
                // cancelled, on a different date, or already full.
                'linked_mass_reservation_id' => ['required', 'integer', Rule::exists('reservations', 'id')],
            ],
            'school_mass' => [
                'details.school_name' => ['required', 'string', 'max:255'],
                'details.school_contact_person' => ['required', 'string', 'max:255'],
                'details.occasion' => ['nullable', Rule::in(['first_friday', 'graduation', 'patron_feast', 'opening_of_school_year', 'other'])],
                'details.venue' => ['required', Rule::in(['on_campus', 'church'])],
                'details.student_volunteers_assigned' => ['boolean'],
                'details.recurring' => ['boolean'],
            ],
            'chapel_mass' => [
                'details.chapel' => ['required', 'string', 'max:255'],
            ],
            'house_blessing' => [
                // Someone has to fetch and return the priest, so the visit
                // address is mandatory here even though it's optional globally.
                'contact_address' => ['required', 'string', 'max:500'],
                'details.transportation_arranged' => ['boolean'],
                'details.reception_planned' => ['boolean'],
            ],
            'first_communion' => [
                // "individual" = a single family registering for the parish's
                // weekend catechism batch. "school_batch" = a school admin
                // booking the Mass slot for a whole Grade 3 batch at once.
                'details.booking_mode' => ['required', Rule::in(['individual', 'school_batch'])],
                'details.child_name' => ['required_if:details.booking_mode,individual', 'nullable', 'string', 'max:255'],
                'details.parish_or_school_program' => ['nullable', 'string', 'max:255'],
                'details.school_name' => ['required_if:details.booking_mode,school_batch', 'nullable', 'string', 'max:255'],
                'details.school_contact_person' => ['required_if:details.booking_mode,school_batch', 'nullable', 'string', 'max:255'],
                'details.communicant_count' => ['required_if:details.booking_mode,school_batch', 'nullable', 'integer', 'min:1'],
            ],
            'confirmation' => [
                'details.confirmand_name' => ['required', 'string', 'max:255'],
                'details.confirmation_name' => ['nullable', 'string', 'max:255'],
                'details.sponsor_name' => ['nullable', 'string', 'max:255'],
            ],
            'business_blessing' => [
                // Same reasoning as house_blessing — the priest travels there.
                'contact_address' => ['required', 'string', 'max:500'],
                'details.business_name' => ['required', 'string', 'max:255'],
                'details.transportation_arranged' => ['boolean'],
            ],
            'vehicle_blessing' => [
                'details.item_description' => ['required', 'string', 'max:255'],
            ],
            'anointing_of_the_sick' => [
                'details.is_emergency' => ['boolean'],
                'details.patient_location' => ['required', 'string', 'max:500'],
            ],
            'spiritual_direction' => [
                'details.topic' => ['nullable', 'string', 'max:1000'],
            ],
            'special_intention' => [
                'details.intention' => ['required', 'string', 'max:1000'],
            ],
            // No shared/conflict-checked venue — whatever the admin types
            // is purely informational (see ChurchAvailabilityService::
            // resolveVenue(), which returns no venue for 'others').
            'others' => [
                'details.location' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    public function attributes(): array
    {
        return [
            'details.groom_name' => "groom's name",
            'details.bride_name' => "bride's name",
            'details.ceremony_type' => 'ceremony type',
            'details.child_name' => "child's name",
            'details.father_name' => "father's name",
            'details.mother_maiden_name' => "mother's maiden name",
            'details.baptism_type' => 'baptism type',
            'details.deceased_name' => "deceased person's name",
            'details.service_type' => 'service type',
            'details.committal_type' => 'committal type',
            'details.school_name' => 'school name',
            'details.school_contact_person' => 'school contact person',
            'details.occasion' => 'occasion',
            'details.venue' => 'venue',
            'details.chapel' => 'chapel / barangay',
            'details.booking_mode' => 'booking type',
            'details.communicant_count' => 'number of communicants',
            'details.confirmand_name' => "confirmand's name",
            'details.business_name' => 'business / office name',
            'details.item_description' => 'vehicle / article description',
            'details.patient_location' => 'hospital room / home address',
            'details.intention' => 'intention / petition',
            'details.location' => 'location / venue',
        ];
    }
}