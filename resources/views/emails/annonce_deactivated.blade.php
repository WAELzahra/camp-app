<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Annonce désactivée</title>
    <style>
        body {
            font-family: Arial;
            background-color: #f9f9f9;
            padding: 20px;
        }

        .container {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            max-width: 600px;
            margin: auto;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #e74c3c;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Bonjour {{ $user->name }},</h2>
        <p>Nous vous informons que votre annonce a été <strong>désactivée par l'administrateur</strong>.</p>
        <p>Description de l’annonce : {{ $annonce->description }}</p>
        <p>Si vous pensez qu’il s’agit d’une erreur, veuillez nous contacter.</p>
        <br>
        <p>Merci de votre compréhension,<br>L'équipe de modération</p>
    </div>
</body>

</html>
