{{-- <!DOCTYPE html>
<html>
<head>
    <title>Ticket de réservation</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .details { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px;}
        th, td { border: 1px solid #000; padding: 8px; text-align: left;}
    </style>
</head>
<body>
    <div class="header">
        <h1>Ticket de réservation</h1>
        <p>Réservation N°: {{ $reservation->id }}</p>
        <p>Date: {{ $reservation->created_at->format('d/m/Y H:i') }}</p>
    </div>

 <div class="details">
    <p><strong>Nom du client :</strong> {{ $reservation->user?->name ?? $reservation->name }}</p>
    <p><strong>Email :</strong> {{ $reservation->user?->email ?? $reservation->email }}</p>

    <p><strong>Événement :</strong> {{ $reservation->event?->title ?? 'Titre non disponible' }}</p>
    <p><strong>Nombre de places :</strong> {{ $reservation->nbr_place }}</p>

    <p><strong>Montant payé :</strong> 
        {{ isset($reservation->payment?->montant) ? number_format($reservation->payment->montant, 2, ',', ' ') . ' Dt' : 'Non renseigné' }}
    </p>
</div>

</body>
</html> --}}

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ticket de Réservation</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .details { margin: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Confirmation de Réservation</h2>
    </div>

    <div class="details">
        <p><strong>Nom du client :</strong> {{ $reservation->user?->name ?? $reservation->name }}</p>
        <p><strong>Email :</strong> {{ $reservation->user?->email ?? $reservation->email ?? 'Non renseigné' }}</p>

        <p><strong>Événement :</strong> {{ $reservation->event->titre ?? 'Non renseigné' }}</p>
        <p><strong>Nombre de places :</strong> {{ $reservation->nbr_place }}</p>
        <p><strong>Montant payé :</strong> 
            {{ $reservation->payment?->montant ? number_format($reservation->payment->montant, 2, ',', ' ') . ' TND' : 'Non renseigné' }}
        </p>
    </div>
</body>
</html>
