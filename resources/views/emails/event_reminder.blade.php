<!DOCTYPE html>
<html>
<head>
    <title>Rappel Événement</title>
</head>
<body>
    <p>Bonjour,</p>

    <p>Ceci est un rappel pour l'événement <strong>{{ $event->title }}</strong> organisé par {{ $event->group->name ?? 'le groupe' }}.</p>

    <p>Date de début : {{ \Carbon\Carbon::parse($event->date_sortie)->format('d/m/Y H:i') }}</p>

    <p>Merci de votre participation !</p>
</body>
</html>
