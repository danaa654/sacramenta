@extends('certificates.layout')

@section('title', 'Death / Burial Certificate')

@section('content')
    @php
        $d = $reservation->details ?? [];
        $serviceLabels = [
            'funeral_mass' => 'Full Funeral Mass',
            'memorial_service' => 'Memorial / Prayer Service',
        ];
    @endphp

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

        <h1 class="cert-title">Death / Burial Certificate</h1>
        <p class="cert-subtitle">Christian Burial Rites</p>

        <p class="cert-body">
            This is to certify that
            <span class="name">{{ $d['deceased_name'] ?? '________________' }}</span>{{ !empty($d['age']) ? ', age '.$d['age'].',' : '' }}
            was given Christian burial rites according to the Roman Catholic Church, through
            <span class="fill">{{ $serviceLabels[$d['service_type'] ?? ''] ?? 'the Funeral Mass' }}</span>,
            on the
            <span class="fill">{{ \Carbon\Carbon::parse($reservation->event_date)->format('jS \d\a\y \o\f F, Y') }}</span>,
            officiated by
            <span class="fill">{{ $reservation->priest?->name ?? '________________' }}</span>,
            with committal at
            <span class="fill">{{ $d['cemetery'] ?? '________________' }}</span>.
        </p>

        <div class="signature-row">
            <div class="signature-line">Officiating Priest</div>
            <div class="signature-line">Parish Seal</div>
        </div>

        <p class="footnote">Issued by Sacramenta on behalf of the parish office. Not valid without the parish seal and signature.</p>
        </div>
    </div>
@endsection