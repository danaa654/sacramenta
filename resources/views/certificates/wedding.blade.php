@extends('certificates.layout')

@section('title', 'Marriage Certificate')

@section('content')
    @php
        $d = $reservation->details ?? [];
        $ceremonyLabels = [
            'nuptial_mass' => 'Nuptial Mass (with Communion)',
            'liturgy_of_the_word' => 'Liturgy of the Word Only (No Mass)',
        ];
    @endphp

    <div class="certificate">
        <img src="{{ asset('img/logo.png') }}" alt="" class="cert-watermark">
        <div class="cert-header">
            <img src="{{ asset('img/logo.png') }}" alt="Parish Logo" class="cert-logo">
            <!-- NOTE: replace with your parish's actual name, address, and logo. -->
            <div class="cert-header-text">
                <p class="parish-name">Sacramenta Parish Office</p>
                <p class="parish-address">Parish Address Line · Contact Number</p>
            </div>
        </div>
        <div class="cert-body-wrap">

        <h1 class="cert-title">Marriage Certificate</h1>
        <p class="cert-subtitle">Sacrament of Holy Matrimony</p>

        <p class="cert-body">
            This is to certify that
            <span class="name">{{ $d['groom_name'] ?? '________________' }}</span>
            and
            <span class="name">{{ $d['bride_name'] ?? '________________' }}</span>
            were united in the Sacrament of Holy Matrimony according to the rites of the
            Roman Catholic Church, on the
            <span class="fill">{{ \Carbon\Carbon::parse($reservation->event_date)->format('jS \d\a\y \o\f F, Y') }}</span>,
            through
            <span class="fill">{{ $ceremonyLabels[$d['ceremony_type'] ?? ''] ?? 'the Nuptial Mass' }}</span>,
            officiated by
            <span class="fill">{{ $reservation->priest?->name ?? '________________' }}</span>.
        </p>

        <hr class="divider">

        <table class="fields">
            <tr>
                <td class="label">Groom</td>
                <td class="value">{{ $d['groom_name'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Bride</td>
                <td class="value">{{ $d['bride_name'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Date of Marriage</td>
                <td class="value">{{ \Carbon\Carbon::parse($reservation->event_date)->format('F j, Y') }}</td>
            </tr>
            <tr>
                <td class="label">Officiating Priest</td>
                <td class="value">{{ $reservation->priest?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Venue</td>
                <td class="value">{{ $reservation->location?->name ?? 'Main Sanctuary' }}</td>
            </tr>
        </table>

        <div class="signature-row">
            <div class="signature-line">Officiating Priest</div>
            <div class="signature-line">Parish Seal</div>
        </div>

        <p class="footnote">Issued by Sacramenta on behalf of the parish office. Not valid without the parish seal and signature.</p>
        </div>
    </div>
@endsection