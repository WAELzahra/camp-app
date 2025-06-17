<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Réservation rejetée</title>
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
            color: #e74c3c;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Bonjour {{ $user->name }},</h2>
        <p>Nous sommes désolés de vous informer que votre réservation a été <strong>rejetée</strong>.</p>
        @if ($reason)
            <p>Raison : {{ $reason }}</p>
        @endif
        <p>Vous pouvez essayer de réserver à une autre date.</p>
        <p>Cordialement,<br>L'équipe de réservation</p>
    </div>
</body>

</html>
