<?php

namespace App\Http\Controllers\Notification;

use App\Models\Events;
use App\Models\Reservations_events;
use Illuminate\Support\Facades\Mail;
use App\Mail\EventReminderMail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
   public function sendRemindersForEvent(Events $event)
{
    // Récupérer les réservations confirmées ET avec paiement payé
    $reservations = Reservations_events::where('event_id', $event->id)
        ->where('status', 'confirmé')  // bien avec l'accent
        ->whereHas('payment', function($query) {
            $query->where('status', 'paid');
        })
        ->with('user')  // pour charger l'utilisateur et son email
        ->get();

    if ($reservations->isEmpty()) {
        \Log::info("Aucune réservation confirmée et payée pour l'événement ID {$event->id}");
        return response()->json(['message' => 'Aucune réservation confirmée et payée pour cet événement']);
    }

    $sentCount = 0;

    foreach ($reservations as $reservation) {
        $user = $reservation->user;
        if (!$user) {
            \Log::warning("Réservation ID {$reservation->id} sans user associé.");
            continue;
        }
        if (empty($user->email)) {
            \Log::warning("Utilisateur ID {$user->id} sans email.");
            continue;
        }

        try {
            \Mail::to($user->email)->send(new \App\Mail\EventReminderMail($event));
            $sentCount++;
        } catch (\Exception $e) {
            \Log::error("Erreur envoi mail à {$user->email} : " . $e->getMessage());
        }
    }

    return response()->json(['message' => "Mails de rappel envoyés à {$sentCount} utilisateur(s)"]);
}

}
