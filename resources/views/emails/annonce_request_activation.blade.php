<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Annonce en attente de validation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f6f9fc;
            color: #333333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            background-color: white;
            padding: 30px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
        }
        p {
            font-size: 16px;
            line-height: 1.5em;
        }
        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #999999;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bonjour {{ $user->name }},</h1>

        <p>Votre annonce a été soumise avec succès.</p>
        <p>Elle est en attente d'activation par un administrateur.</p>

        <p>Vous recevrez une notification dès que l’activation sera faite.</p>

        <div class="footer">
            <p>Cordialement,<br>L'équipe de support</p>
        </div>
    </div>
</body>
</html>
