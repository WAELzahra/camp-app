<!DOCTYPE html>
<html>
<head>
    <title>Mise à jour de votre réservation</title>
</head>
<body>
    <p>Bonjour {{ $reservation->user->name }},</p>

    <p>Le statut de votre réservation pour l'événement "{{ $reservation->event->title }}" a été mis à jour.</p>

    <p>Nouveau statut : <strong>{{ $reservation->status }}</strong></p>

    <p>Merci de votre confiance !</p>
</body>
</html>
