<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinancialsController extends Controller
{
    protected array $typeLabels = [
        'wedding' => 'Wedding',
        'baptism' => 'Baptism',
        'burial' => 'Burial',
        'first_communion' => 'First Communion',
        'confirmation' => 'Confirmation',
        'pamisa_sa_kalag' => 'Pamisa sa Kalag',
        'chapel_mass' => 'Chapel Mass',
        'school_mass' => 'School Mass',
        'house_blessing' => 'House Blessing',
        'business_blessing' => 'Business / Office Blessing',
        'vehicle_blessing' => 'Vehicle / Article Blessing',
        'anointing_of_the_sick' => 'Anointing of the Sick',
        'spiritual_direction' => 'Spiritual Direction / Confession',
        'special_intention' => 'Special Intention / Petition',
        'others' => 'Others',
    ];

    /**
     * Ledger view: every reservation that carries (or could carry) an
     * offering/stipend, with running totals for the currently applied
     * filters. Reservations with no offering_amount at all are excluded
     * so the ledger doesn't get cluttered with $0 rows.
     */
    public function index(Request $request): Response
    {
        $query = Reservation::with('priest')
            ->whereNotNull('offering_amount')
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->when($request->string('payment_status')->toString(), fn ($q, $status) => $q->where('payment_status', $status))
            ->when($request->string('from')->toString(), fn ($q, $from) => $q->whereDate('event_date', '>=', $from))
            ->when($request->string('to')->toString(), fn ($q, $to) => $q->whereDate('event_date', '<=', $to))
            ->when($request->string('search')->toString(), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('contact_name', 'like', "%{$search}%")
                        ->orWhere('receipt_number', 'like', "%{$search}%");
                });
            });

        $reservations = (clone $query)
            ->orderByDesc('event_date')
            ->paginate(20)
            ->withQueryString();

        // Totals computed over the *filtered* set (not just the current page).
        $totalsSource = (clone $query)->get(['offering_amount', 'amount_paid', 'payment_status']);

        $totals = [
            'expected' => (float) $totalsSource->sum('offering_amount'),
            'collected' => (float) $totalsSource->sum('amount_paid'),
            'outstanding' => (float) $totalsSource->sum(fn ($r) => max(0, $r->offering_amount - $r->amount_paid)),
            'count' => $totalsSource->count(),
            'paidCount' => $totalsSource->where('payment_status', 'paid')->count(),
            'unpaidCount' => $totalsSource->whereIn('payment_status', ['unpaid', 'partial'])->count(),
        ];

        return Inertia::render('Financials/Index', [
            'reservations' => $reservations,
            'totals' => $totals,
            'filters' => $request->only(['type', 'payment_status', 'from', 'to', 'search']),
            'typeLabels' => $this->typeLabels,
        ]);
    }

    /**
     * Record/update payment details for a single reservation from the
     * ledger row (receipt no., amount paid, status, date, note).
     */
    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'payment_status' => ['required', Rule::in(['unpaid', 'partial', 'paid', 'waived'])],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
            'payment_note' => ['nullable', 'string', 'max:255'],
        ]);

        $reservation->update($validated);

        return back()->with('success', 'Payment record updated.');
    }
}