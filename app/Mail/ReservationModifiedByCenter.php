<?php

namespace App\Mail;

use App\Models\Reservations_centre;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationModifiedByCenter extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;
    public $modificationData;
    public $frontendUrl;
    public $supportEmail;

    public function __construct(Reservations_centre $reservation, array $modificationData)
    {
        $this->reservation = $reservation;
        $this->modificationData = $modificationData;
        $this->frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $this->supportEmail = config('mail.support_email', 'support@tunisiacamp.com');
    }

    public function build()
    {
        // Get rejected services (status = 'rejected', rejected_by = 'center')
        $rejectedServices = $this->reservation->serviceItems
            ->where('status', 'rejected')
            ->where('rejected_by', 'center');
            
        // Get accepted services (status = 'approved')
        $acceptedServices = $this->reservation->serviceItems
            ->where('status', 'approved');
        
        // Format dates
        $startDate = Carbon::parse($this->reservation->date_debut);
        $endDate = Carbon::parse($this->reservation->date_fin);
        $duration = $endDate->diffInDays($startDate) + 1;
        
        return $this->subject('Reservation Modified - TunisiaCamp')
            ->markdown('emails.reservation-modified')
            ->with([
                'reservation' => $this->reservation,
                'rejectedServices' => $rejectedServices,
                'acceptedServices' => $acceptedServices,
                'frontendUrl' => $this->frontendUrl,
                'supportEmail' => $this->supportEmail,
                'generalReason' => $this->modificationData['general_reason'] ?? null,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'duration' => $duration,
                'modificationDate' => $this->reservation->last_modified_at ?? now(),
            ]);
    }
}