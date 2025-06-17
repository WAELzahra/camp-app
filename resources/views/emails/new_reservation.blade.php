<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Nouvelle réservation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            padding: 20px;
        }

        .content {
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
    </style>
</head>

<body>
    <div class="content">
        <h2>Bonjour {{ $centre->name }},</h2>
        <p>Vous avez reçu une nouvelle réservation de la part de <strong>{{ $user->name }}</strong>.</p>
        <p><strong>Date de début :</strong> {{ $reservation->date_debut }}</p>
        <p><strong>Date de fin :</strong> {{ $reservation->date_fin }}</p>
        <p><strong>Type :</strong> {{ $reservation->type }}</p>
        <p><strong>Nombre de places :</strong> {{ $reservation->nbr_place }}</p>
        @if ($reservation->note)
            <p><strong>Note :</strong> {{ $reservation->note }}</p>
        @endif
        <p>Merci,<br>L'équipe</p>
    </div>
</body>

</html>
