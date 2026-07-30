@component('mail::message')
@php
$statusLabels = [
    'en_attente_paiement'        => 'En attente de paiement',
    'confirmée'                  => 'Confirmée',
    'en_attente_validation'      => 'En attente de validation',
    'refusée'                    => 'Refusée',
    'annulée_par_utilisateur'    => 'Annulée par vous',
    'annulée_par_organisateur'   => 'Annulée par l\'organisateur',
    'remboursement_en_attente'   => 'Remboursement en attente',
    'remboursée_partielle'       => 'Remboursée partiellement',
    'remboursée_totale'          => 'Remboursée intégralement',
    'paiement_soumis'            => 'Paiement soumis — vérification en cours',
    'paiement_invalide'          => 'Paiement invalide',
    'confirmée_solde_en_attente' => 'Confirmée — solde restant dû',
    'solde_soumis'               => 'Solde soumis — vérification en cours',
    'entièrement_payée'          => 'Entièrement payée',
    'annulée_solde_impayé'       => 'Annulée — solde impayé',
    'modified'                   => 'Modifiée',
    'pending'                    => 'En attente',
    'pending_payment'            => 'En attente de paiement',
];
$statusLabel = $statusLabels[$reservation->status] ?? ucfirst($reservation->status);
@endphp

# Statut de réservation mis à jour

Bonjour **{{ $reservation->name ?? 'cher participant' }}**,

Le statut de votre réservation pour **{{ $event->title ?? 'l\'événement' }}** a été mis à jour.

@component('mail::panel')
**N° de réservation :** #{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}
**Événement :** {{ $event->title ?? 'N/A' }}
**Date de l'événement :** {{ \Carbon\Carbon::parse($event->start_date ?? $event->date_debut ?? now())->locale('fr')->translatedFormat('d/m/Y') }}
**Places :** {{ $reservation->nbr_place }}
**Nouveau statut :** {{ $statusLabel }}
@endcomponent

@if($reservation->status === 'confirmée')
Votre place est confirmée ! Nous avons hâte de vous voir à l'événement.
@elseif($reservation->status === 'refusée')
Malheureusement, votre réservation n'a pas été acceptée. Vous pouvez consulter d'autres événements sur TunisiaCamp.
@elseif(in_array($reservation->status, ['en_attente_paiement', 'pending_payment']))
Merci de compléter votre paiement pour sécuriser votre place.
@endif

@component('mail::button', ['url' => $frontendUrl . '/profile/reservations', 'color' => 'primary'])
Voir mes réservations
@endcomponent

Cordialement,
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Ceci est un message automatique. Réservation n° {{ $reservation->id }} · {{ now()->locale('fr')->translatedFormat('d/m/Y H:i') }}
@endcomponent
@endcomponent
