<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Boutique supprimée</title>
</head>

<body>
    <p>Bonjour {{ $user->name }},</p>

    <p>Nous confirmons que votre boutique <strong>{{ $boutiqueName }}</strong> a été supprimée avec succès.</p>

    <p>Si vous avez supprimé votre boutique par erreur ou souhaitez en créer une nouvelle, vous pouvez le faire depuis
        votre compte.</p>

    <p>Merci de votre confiance,<br>L'équipe CampApp</p>
</body>

</html>
