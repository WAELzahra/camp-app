<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Confirmation de réservation</title>
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
            color: #2ecc71;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Bonjour {{ $user->name }},</h2>
        <p>Votre réservation a été <strong>confirmée</strong>.</p>
        <p>Merci pour votre confiance.</p>
        <p>Cordialement,<br>L'équipe de réservation</p>
    </div>
</body>

</html>
