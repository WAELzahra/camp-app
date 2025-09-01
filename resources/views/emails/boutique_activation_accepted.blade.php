<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Boutique activée</title>
</head>

<body>
    <p>Bonjour {{ $user->name }},</p>

    <p>Félicitations 🎉 ! Votre boutique <strong>{{ $boutiqueName }}</strong> a été <strong>activée</strong>.</p>

    <p>Elle est désormais visible par tous les utilisateurs, et vous pouvez commencer à publier vos annonces et recevoir
        vos premiers clients.</p>

    <p>Nous vous souhaitons beaucoup de succès,<br>L'équipe CampApp</p>
</body>

</html>
