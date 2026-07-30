@component('mail::message')
# Action requise : retour de matériel en retard

Bonjour **{{ $reservation->user->first_name ?? 'cher client' }}**,

Nos registres montrent que vous n'avez pas restitué le matériel loué avant la date de retour convenue. Votre réservation a été marquée comme **en litige**.

@component('mail::panel')
**N° de réservation :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Article :** {{ $reservation->materielle->nom ?? 'N/A' }}
**Fournisseur :** {{ ($reservation->fournisseur->first_name ?? '') . ' ' . ($reservation->fournisseur->last_name ?? '') }}
**Date limite de retour :** {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
**Statut :** En litige — retour en retard
@endcomponent

Veuillez contacter le fournisseur ou le support TunisiaCamp immédiatement pour résoudre ce problème.

Le défaut de restitution du matériel peut entraîner des poursuites conformément à la législation tunisienne.

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'error'])
Voir mes réservations
@endcomponent

Pour toute assistance, contactez-nous à [{{ $supportEmail }}](mailto:{{ $supportEmail }}).

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est un message automatique. Réservation n° {{ $reservation->id }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
