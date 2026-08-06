@extends('certificates.layout')

@section('title', 'Baptismal Certificate')

@section('content')
    @php
        $d = $reservation->details ?? [];
        $isGroup = ($d['baptism_type'] ?? 'individual') === 'group';
        $children = $isGroup
            ? (is_array($d['children'] ?? null) ? $d['children'] : [])
            : [[
                'child_name' => $d['child_name'] ?? '',
                'father_name' => $d['father_name'] ?? '',
                'mother_maiden_name' => $d['mother_maiden_name'] ?? '',
                'godparents' => $d['godparents'] ?? [],
            ]];
        if (empty($children)) {
            $children = [['child_name' => '', 'father_name' => '', 'mother_maiden_name' => '', 'godparents' => []]];
        }
    @endphp

    @foreach ($children as $child)
        @php
            $godparentNames = collect($child['godparents'] ?? [])
                ->pluck('name')
                ->filter()
                ->implode(', ');
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

            <h1 class="cert-title">Baptismal Certificate</h1>
            <p class="cert-subtitle">Sacrament of Holy Baptism</p>

            <p class="cert-body">
                This is to certify that
                <span class="name">{{ $child['child_name'] ?: '________________' }}</span>,
                child of
                <span class="fill">{{ $child['father_name'] ?: '________________' }}</span>
                and
                <span class="fill">{{ $child['mother_maiden_name'] ?: '________________' }}</span>,
                was baptized according to the rites of the Roman Catholic Church on the
                <span class="fill">{{ \Carbon\Carbon::parse($reservation->event_date)->format('jS \d\a\y \o\f F, Y') }}</span>,
                by
                <span class="fill">{{ $reservation->priest?->name ?? '________________' }}</span>,
                with
                <span class="fill">{{ $godparentNames ?: '________________' }}</span>
                standing as godparent(s).
            </p>

            <hr class="divider">

            <table class="fields">
                <tr>
                    <td class="label">Child's Name</td>
                    <td class="value">{{ $child['child_name'] ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Father's Name</td>
                    <td class="value">{{ $child['father_name'] ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Mother's Maiden Name</td>
                    <td class="value">{{ $child['mother_maiden_name'] ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Godparent(s)</td>
                    <td class="value">{{ $godparentNames ?: '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Date of Baptism</td>
                    <td class="value">{{ \Carbon\Carbon::parse($reservation->event_date)->format('F j, Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Officiating Priest</td>
                    <td class="value">{{ $reservation->priest?->name ?? '—' }}</td>
                </tr>
            </table>

            <div class="signature-row">
                <div class="signature-line">Officiating Priest</div>
                <div class="signature-line">Parish Seal</div>
            </div>

            <p class="footnote">Issued by Sacramenta on behalf of the parish office. Not valid without the parish seal and signature.</p>
            </div>
        </div>
    @endforeach
@endsection