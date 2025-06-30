<h1>Votre réservation est confirmée !</h1>
<p>Bonjour {{ $reservation->user->name }},</p>
<p>Votre réservation pour l’événement <strong>{{ $reservation->event->description }}</strong> a été confirmée.</p>
<p>Nombre de places : {{ $reservation->nbr_place }}</p>
<p>Merci pour votre confiance.</p>
