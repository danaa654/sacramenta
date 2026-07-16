<?php

namespace App\Http\Requests;

use App\Models\Priest;
use App\Services\SchedulingConflictService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_mobile' => ['required', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'event_date' => ['required', 'date'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'priest_id' => ['nullable', 'exists:priests,id'],
            'offering_amount' => ['nullable', 'numeric', 'min:0'],
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
            $this->checkSchedulingConflict($validator);
        });
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

        $priestId = $this->input('priest_id');

        if ($priestId) {
            $conflict = $service->findPriestConflict(
                $priestId,
                $date,
                $time,
                $type,
                $currentReservation?->id
            );

            if ($conflict) {
                $priestName = Priest::find($priestId)?->name ?? 'The priest';
                $conflictTime = Carbon::parse($conflict->event_time)->format('g:i A');
                $conflictDate = $conflict->event_date->format('F j, Y');

                $validator->errors()->add(
                    'event_time',
                    "{$priestName} already has a confirmed reservation at {$conflictTime} on {$conflictDate}."
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
                $currentReservation?->id
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
                'details.rehearsal_date' => ['nullable', 'date'],
            ],
            'baptism' => [
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
                'details.service_type' => ['required', Rule::in(['funeral_mass', 'funeral_service'])],
                'details.scripture_readings' => ['nullable', 'string', 'max:1000'],
                'details.songs' => ['nullable', 'string', 'max:1000'],
                'details.has_eulogy' => ['boolean'],
                'details.committal_type' => ['nullable', Rule::in(['cemetery', 'crematorium'])],
                'details.cemetery' => ['nullable', 'string', 'max:255'],
            ],
            'pamisa_sa_kalag' => [
                'details.names' => ['required', 'string'],
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
            default => [],
        };
    }

    public function attributes(): array
    {
        return [
            'details.groom_name' => "groom's name",
            'details.bride_name' => "bride's name",
            'details.ceremony_type' => 'ceremony type',
            'details.rehearsal_date' => 'rehearsal date',
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
        ];
    }
}