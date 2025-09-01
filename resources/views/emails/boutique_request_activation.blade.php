<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Boutique en attente de validation</title>
</head>

<body style="font-family: Arial, sans-serif; color: #333;">
    <h2>Demande d’activation de boutique</h2>

    <p>Bonjour,</p>

    <p>
        L’utilisateur <strong>{{ $user->name }}</strong>
        (email : {{ $user->email }}) a demandé l’activation de sa boutique.
    </p>

    <p>
        Veuillez vérifier ses informations et procéder à la validation depuis l’espace d’administration.
    </p>

    <p style="margin-top: 20px;">Merci,<br>L’équipe {{ config('app.name') }}</p>
</body>

</html>
