@component('mail::message')
# Alerte : location en retard

Bonjour **{{ $reservation->fournisseur->first_name ?? 'cher fournisseur' }}**,

Un campeur n'a pas restitué votre matériel avant la date de retour convenue. La réservation a été automatiquement marquée comme **en litige**.

@component('mail::panel')
**N° de réservation :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Article :** {{ $reservation->materielle->nom ?? 'N/A' }}
**Campeur :** {{ ($reservation->user->first_name ?? '') . ' ' . ($reservation->user->last_name ?? '') }}
**E-mail du campeur :** {{ $reservation->user->email ?? 'N/A' }}
**Date limite de retour :** {{ \Carbon\Carbon::parse($reservation->date_fin)->locale('fr')->translatedFormat('d/m/Y') }}
**Statut :** En litige — retour en retard
@endcomponent

Veuillez contacter directement le campeur ou le support TunisiaCamp pour résoudre ce problème.

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
