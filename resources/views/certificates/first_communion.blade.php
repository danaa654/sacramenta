@extends('certificates.layout')

@section('title', 'First Communion Certificate')

@section('content')
    @php
        $d = $reservation->details ?? [];
        $isGroup = ($d['booking_mode'] ?? 'individual') === 'school_batch';
        $names = $isGroup
            ? collect($d['students'] ?? [])->pluck('name')->filter()->values()->all()
            : [$d['child_name'] ?? ''];
        if (empty($names)) {
            $names = [''];
        }
        $program = $isGroup ? ($d['school_name'] ?? '') : ($d['parish_or_school_program'] ?? '');
    @endphp

    @foreach ($names as $childName)
        <div class="certificate">
            <img src="{{ asset('img/logo.png') }}" alt="" class="cert-watermark">
            <div class="cert-header">
                <img src="{{ asset('img/logo.png') }}" alt="Parish Logo" class="cert-logo">
                <!-- NOTE: replace with your parish's actual name, address, and logo. -->
                <div class="cert-header-text">
                    <p class="parish-name">Parish of the Holy Sacraments</p>
                    <p class="parish-address">Parish Address Line · Contact Number</p>
                </div>
            </div>
            <div class="cert-body-wrap">

            <h1 class="cert-title">First Communion Certificate</h1>
            <p class="cert-subtitle">Sacrament of the Holy Eucharist</p>

            <p class="cert-body">
                This is to certify that
                <span class="name">{{ $childName ?: '________________' }}</span>
                received the Sacrament of the Holy Eucharist for the first time on the
                <span class="fill">{{ \Carbon\Carbon::parse($reservation->event_date)->format('jS \d\a\y \o\f F, Y') }}</span>,
                at
                <span class="fill">{{ $program ?: '________________' }}</span>,
                administered by
                <span class="fill">{{ $reservation->priest?->name ?? '________________' }}</span>.
            </p>

            <div class="signature-row">
                <div class="signature-line">Officiating Priest</div>
                <div class="signature-line">Parish Seal</div>
            </div>

            <p class="footnote">Issued by Sacramenta on behalf of the parish office. Not valid without the parish seal and signature.</p>
            </div>
        </div>
    @endforeach
@endsection