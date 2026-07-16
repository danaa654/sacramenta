@php
    $typeLabels = [
        'wedding' => 'Wedding',
        'baptism' => 'Baptism',
        'burial' => 'Burial',
        'pamisa_sa_kalag' => 'Pamisa sa Kalag',
        'chapel_mass' => 'Chapel Mass',
        'school_mass' => 'School Mass',
        'house_blessing' => 'House Blessing',
        'others' => 'Others',
    ];
@endphp

<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #2f4a4a; background:#f5f2ea; padding: 24px;">
    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #eee;">
        <p style="text-transform: uppercase; letter-spacing: 0.15em; font-size: 11px; color: #8CA089; font-weight: 600;">
            Sacramenta
        </p>
        <h2 style="color: #3f6470; font-size: 22px; margin-top: 4px;">
            Your reservation is confirmed
        </h2>

        <p>Hello {{ $reservation->contact_name }},</p>

        <p>
            This confirms your <strong>{{ $typeLabels[$reservation->type] ?? $reservation->type }}</strong>
            reservation with the parish office.
        </p>

        <table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
            <tr>
                <td style="padding: 6px 0; color: #3f6470;">Date</td>
                <td style="padding: 6px 0; font-weight: 600;">{{ \Carbon\Carbon::parse($reservation->event_date)->format('F j, Y') }}</td>
            </tr>
            @if($reservation->event_time)
            <tr>
                <td style="padding: 6px 0; color: #3f6470;">Time</td>
                <td style="padding: 6px 0; font-weight: 600;">{{ \Carbon\Carbon::parse($reservation->event_time)->format('g:i A') }}</td>
            </tr>
            @endif
            @if($reservation->priest)
            <tr>
                <td style="padding: 6px 0; color: #3f6470;">Priest</td>
                <td style="padding: 6px 0; font-weight: 600;">{{ $reservation->priest->name }}</td>
            </tr>
            @endif
        </table>

        <p>
            If anything above looks incorrect, or you need to make changes, please contact the
            parish office directly.
        </p>

        <p style="margin-top: 32px; font-size: 12px; color: #999;">
            This is an automated message from Sacramenta.
        </p>
    </div>
</body>
</html>