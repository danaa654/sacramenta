@php
    $typeLabels = [
        'wedding' => 'Wedding',
        'baptism' => 'Baptism',
        'burial' => 'Burial',
        'pamisa_sa_kalag' => 'Pamisa sa Kalag',
        'chapel_mass' => 'Chapel Mass',
        'school_mass' => 'School Mass',
        'first_communion' => 'First Communion',
        'house_blessing' => 'House Blessing',
        'others' => 'Others',
    ];

    // amount_paid is Eloquent-cast as decimal:2, which returns a *string*
    // like "0.00" — and "0.00" is truthy in PHP (only "" and "0" are
    // falsy), so a naive `$amount_paid ?: $offering_amount` never falls
    // back and silently shows ₱0.00 whenever no payment amount was
    // separately logged. Compare numerically instead. If the status was
    // marked "Paid" but no amount_paid was recorded, treat the full
    // offering as received.
    $amountPaid = (float) ($reservation->amount_paid ?? 0);
    $offering = (float) ($reservation->offering_amount ?? 0);
    $amount = $amountPaid > 0
        ? $amountPaid
        : ($reservation->payment_status === 'paid' ? $offering : $amountPaid);


    // Simple peso amount-in-words, the way a physical Official Receipt
    // usually spells it out. Only needs to handle typical offering/stipend
    // amounts, not arbitrary huge numbers.
    $numberToWords = function (int $number) use (&$numberToWords) {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        if ($number < 20) {
            return $ones[$number];
        }
        if ($number < 100) {
            return trim($tens[intdiv($number, 10)].' '.$ones[$number % 10]);
        }
        if ($number < 1000) {
            return trim($ones[intdiv($number, 100)].' Hundred '.$numberToWords($number % 100));
        }
        if ($number < 1000000) {
            return trim($numberToWords(intdiv($number, 1000)).' Thousand '.$numberToWords($number % 1000));
        }

        return trim($numberToWords(intdiv($number, 1000000)).' Million '.$numberToWords($number % 1000000));
    };

    $pesos = (int) floor($amount);
    $centavos = (int) round(($amount - $pesos) * 100);
    $amountInWords = ($pesos > 0 ? $numberToWords($pesos).' Pesos' : 'Zero Pesos')
        .($centavos > 0 ? ' and '.$numberToWords($centavos).' Centavos' : '')
        .' Only';

    $statusLabels = [
        'unpaid' => 'Unpaid',
        'partial' => 'Partially Paid',
        'paid' => 'Paid',
        'waived' => 'Waived',
    ];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Official Receipt @if($reservation->receipt_number) — {{ $reservation->receipt_number }} @endif</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            color: #2f4a4a;
            background: #f5f2ea;
            margin: 0;
            padding: 32px 16px;
        }
        .toolbar {
            max-width: 640px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: flex-end;
        }
        .print-btn {
            font-family: Arial, sans-serif;
            background: #8CA089;
            color: #fff;
            border: none;
            border-radius: 999px;
            padding: 10px 24px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: pointer;
        }
        .print-btn:hover { background: #7c9078; }
        .receipt {
            position: relative;
            max-width: 640px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e4dfd2;
            border-radius: 16px;
            padding: 40px 48px;
            overflow: hidden;
        }
        .receipt-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 380px;
            max-width: 65%;
            opacity: 0.06;
            pointer-events: none;
            z-index: 0;
        }
        .receipt-header {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .receipt-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .receipt-body-wrap {
            position: relative;
            z-index: 1;
        }
        .parish-name {
            text-transform: uppercase;
            letter-spacing: 0.15em;
            font-size: 12px;
            color: #8CA089;
            font-weight: 600;
            font-family: Arial, sans-serif;
            margin: 0;
        }
        .parish-address {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #7a8a8a;
            margin: 4px 0 0 0;
        }
        h1 {
            color: #3f6470;
            font-size: 24px;
            margin: 20px 0 4px 0;
            text-align: center;
            letter-spacing: 0.02em;
        }
        .or-number {
            text-align: center;
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #3f6470;
            margin-bottom: 24px;
        }
        .divider {
            border-top: 1px dashed #d8d2c2;
            margin: 20px 0;
        }
        table.fields { width: 100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 14px; }
        table.fields td { padding: 7px 0; vertical-align: top; }
        table.fields td.label { width: 190px; color: #7a8a8a; text-transform: uppercase; font-size: 11px; letter-spacing: 0.06em; }
        table.fields td.value { font-weight: 600; color: #2f4a4a; }
        .amount-box {
            margin-top: 24px;
            padding: 16px 20px;
            background: #f5f2ea;
            border-radius: 12px;
            font-family: Arial, sans-serif;
        }
        .amount-box .amount {
            font-size: 28px;
            font-weight: 700;
            color: #3f6470;
        }
        .amount-box .words {
            margin-top: 4px;
            font-size: 12px;
            font-style: italic;
            color: #7a8a8a;
        }
        .status-badge {
            display: inline-block;
            font-family: Arial, sans-serif;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 4px 10px;
            border-radius: 999px;
            background: #E4EDE1;
            color: #4a6b47;
        }
        .signature-row {
            margin-top: 56px;
            display: flex;
            justify-content: space-between;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #7a8a8a;
        }
        .signature-line {
            width: 220px;
            border-top: 1px solid #b9c2c2;
            padding-top: 6px;
            text-align: center;
        }
        .footnote {
            margin-top: 28px;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #a3a3a3;
            text-align: center;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .receipt { border: none; border-radius: 0; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="print-btn" onclick="window.print()">Print Receipt</button>
    </div>

    <div class="receipt">
        <img src="{{ asset('img/logo.png') }}" alt="" class="receipt-watermark">
        <div class="receipt-header">
            <img src="{{ asset('img/logo.png') }}" alt="Parish Logo" class="receipt-logo">
            <!-- NOTE: replace with your parish's actual name, address, and logo. -->
            <div>
                <p class="parish-name">Sacramenta Parish Office</p>
                <p class="parish-address">Parish Address Line · Contact Number</p>
            </div>
        </div>

        <div class="receipt-body-wrap">
        <h1>Official Receipt</h1>
        <p class="or-number">
            O.R. No. {{ $reservation->receipt_number ?? 'PENDING' }}
        </p>

        <div class="divider"></div>

        <table class="fields">
            <tr>
                <td class="label">Date</td>
                <td class="value">
                    {{ optional($reservation->payment_date ?? now())->format('F j, Y') }}
                </td>
            </tr>
            <tr>
                <td class="label">Received From</td>
                <td class="value">{{ $reservation->contact_name }}</td>
            </tr>
            <tr>
                <td class="label">Payment For</td>
                <td class="value">
                    {{ $typeLabels[$reservation->type] ?? $reservation->type }} Reservation
                    — {{ \Carbon\Carbon::parse($reservation->event_date)->format('F j, Y') }}
                </td>
            </tr>
            @if($reservation->payment_note)
            <tr>
                <td class="label">Reference / Note</td>
                <td class="value">{{ $reservation->payment_note }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Status</td>
                <td class="value">
                    <span class="status-badge">{{ $statusLabels[$reservation->payment_status] ?? ucfirst($reservation->payment_status ?? 'unpaid') }}</span>
                </td>
            </tr>
        </table>

        <div class="amount-box">
            <div class="amount">₱{{ number_format($amount, 2) }}</div>
            <div class="words">{{ $amountInWords }}</div>
        </div>

        <div class="signature-row">
            <div class="signature-line">Received By</div>
            <div class="signature-line">Payer's Signature</div>
        </div>

        <p class="footnote">This receipt was generated by Sacramenta on behalf of the parish office.</p>
        </div>
    </div>
</body>
</html>