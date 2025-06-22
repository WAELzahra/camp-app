@component('mail::message')
    # Réservation annulée

    Bonjour {{ $fournisseur->name }},

    Nous vous informons que la réservation suivante a été **annulée par le client** :

    - **Client :** {{ $user->name }}
    - **Matériel ID :** {{ $reservation->materielle_id }}
    - **Dates :** du {{ $reservation->date_debut }} au {{ $reservation->date_fin }}
    - **Quantité :** {{ $reservation->quantite }}
    - **Montant :** {{ $reservation->montant_payer }} TND

    Merci de votre compréhension.

    @component('mail::button', ['url' => config('app.url')])
        Accéder à votre tableau de bord
    @endcomponent

    Cordialement,
    L’équipe {{ config('app.name') }}
@endcomponent
