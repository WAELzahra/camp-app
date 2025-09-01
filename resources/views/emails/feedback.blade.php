{{-- resources/views/emails/feedback.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Feedback Notification</title>
</head>
<body>
    <h2>Bonjour {{ $feedback->user->name ?? 'Utilisateur' }},</h2>

    @if($action === 'deleted')
        <p>Votre feedback (« {{ $feedback->contenu }} ») a été supprimé par un administrateur.</p>
    @elseif($action === 'created')
        <p>Merci d’avoir laissé un feedback ! Votre note : <strong>{{ $feedback->note }}/5</strong>.</p>
    @elseif($action === 'updated')
        <p>Votre feedback a été mis à jour avec succès.</p>
    @else
        <p>Vous avez reçu une notification concernant votre feedback.</p>
    @endif

    <br>
    <p>Merci,</p>
    <p><strong>L’équipe Support</strong></p>
</body>
</html>
