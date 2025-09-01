<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Feedback supprimé</title>
</head>

<body>
    <h2>Bonjour {{ $feedback->user->name }},</h2>
    <p>Nous vous informons que votre feedback a été supprimé par un administrateur.</p>

    <p><strong>Note :</strong> {{ $feedback->note }}</p>
    @if ($feedback->contenu)
        <p><strong>Contenu :</strong> {{ $feedback->contenu }}</p>
    @endif

    <p>Merci de votre compréhension.</p>
    <p>L'équipe {{ config('app.name') }}</p>
</body>

</html>
