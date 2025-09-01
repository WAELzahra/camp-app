<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Boutique désactivée</title>
</head>

<body>
    <p>Bonjour {{ $user->name }},</p>

    <p>Nous vous informons que votre boutique <strong>{{ $boutiqueName }}</strong> a été <strong>désactivée</strong>.
    </p>

    <p>Vous ne pourrez plus recevoir de nouvelles annonces ou clients tant qu’elle restera désactivée.</p>

    <p>Si vous pensez qu’il s’agit d’une erreur, ou si vous souhaitez réactiver votre boutique, veuillez contacter notre
        équipe.</p>

    <p>Merci de votre compréhension,<br>L'équipe CampApp</p>
</body>

</html>
