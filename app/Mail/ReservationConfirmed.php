<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Your reservation has been confirmed — Sacramenta')
            ->view('emails.reservation-confirmed');
    }
}