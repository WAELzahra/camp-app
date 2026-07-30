@component('mail::message')
# Réservation rejetée

Bonjour {{ $user->first_name ?? 'cher client' }},

Votre demande de réservation a été rejetée.

**Motif :** {{ $motif }}

Vous pouvez toujours réserver à une autre date.

Cordialement,
**L'équipe TunisiaCamp**
@endcomponent
