<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nouveau événement</title>
</head>
<body>
    <h1>Bonjour {{ $userName }},</h1>
    <p>Un nouveau événement a été créé par un groupe que vous suivez !</p>
    <p><strong>Catégorie :</strong> {{ $event->category }}</p>
    <p><strong>Date de sortie :</strong> {{ $event->date_sortie }}</p>
    <p><strong>Date de retour :</strong> {{ $event->date_retoure }}</p>
    <p><strong>Prix par place :</strong> {{ $event->prix_place }} TND</p>

    <p>Connectez-vous sur notre plateforme pour en savoir plus et réserver votre place !</p>

    <p>À bientôt,<br>L’équipe TunisiaCamp </p>
</body>
</html>
