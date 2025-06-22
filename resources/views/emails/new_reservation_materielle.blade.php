<h2>Nouvelle Réservation</h2>
<p>Bonjour,</p>
<p>Vous avez reçu une nouvelle réservation de la part de {{ $user->name }}.</p>
<ul>
    <li>Type : {{ $reservation->type }}</li>
    <li>Dates : {{ $reservation->date_debut }} → {{ $reservation->date_fin }}</li>
    <li>quantite : {{ $reservation->quantite }}</li>
</ul>
<p>Merci de confirmer ou de rejeter cette demande.</p>
