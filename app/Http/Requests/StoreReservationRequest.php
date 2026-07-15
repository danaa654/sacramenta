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
                'wedding', 'baptism', 'burial', 'pamisa_sa_kalag',
                'school_mass', 'chapel_mass', 'house_blessing', 'others',
            ])],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_mobile' => ['required', 'string', 'max:30'],
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
                'details.canonical_interview' => ['boolean'],
                'details.marriage_banns' => ['boolean'],
            ],
            'baptism' => [
                'details.child_name' => ['required', 'string', 'max:255'],
                'details.father_name' => ['required', 'string', 'max:255'],
                'details.mother_maiden_name' => ['required', 'string', 'max:255'],
                'details.godparents' => ['nullable', 'array'],
                'details.godparents.*.name' => ['required_with:details.godparents', 'string', 'max:255'],
            ],
            'burial' => [
                'details.deceased_name' => ['required', 'string', 'max:255'],
                'details.age' => ['nullable', 'integer', 'min:0', 'max:150'],
                'details.cause_of_death' => ['nullable', 'string', 'max:255'],
                'details.cemetery' => ['nullable', 'string', 'max:255'],
            ],
            'pamisa_sa_kalag' => [
                'details.names' => ['required', 'string'],
            ],
            'school_mass' => [
                'details.school_name' => ['required', 'string', 'max:255'],
                'details.school_contact_person' => ['required', 'string', 'max:255'],
                'details.recurring' => ['boolean'],
            ],
            'chapel_mass' => [
                'details.chapel' => ['required', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    public function attributes(): array
    {
        return [
            'details.groom_name' => "groom's name",
            'details.bride_name' => "bride's name",
            'details.child_name' => "child's name",
            'details.father_name' => "father's name",
            'details.mother_maiden_name' => "mother's maiden name",
            'details.deceased_name' => "deceased person's name",
            'details.school_name' => 'school name',
            'details.school_contact_person' => 'school contact person',
            'details.chapel' => 'chapel / barangay',
        ];
    }
}