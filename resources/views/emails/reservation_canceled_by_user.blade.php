<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Réservation annulée</title>
    <style>
        body {
            font-family: Arial;
            background: #f6f9fc;
            color: #333;
            padding: 20px;
        }

        .container {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            margin: auto;
        }

        h2 {
            color: #f39c12;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Bonjour {{ $center->name }},</h2>
        <p>L'utilisateur <strong>{{ $user->name }}</strong> a annulé sa réservation.</p>
        <p>Merci de prendre les mesures nécessaires.</p>
        <p>Cordialement,<br>L'équipe de réservation</p>
    </div>
</body>

</html>
