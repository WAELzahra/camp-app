@component('mail::message')
# Nouvelle annonce en attente de validation

Bonjour **{{ $user?->first_name ?? $user?->name ?? 'cher utilisateur' }}**,

Nous vous remercions d'avoir soumis votre annonce sur **TunisiaCamp**. Votre annonce est maintenant en attente de validation par notre équipe.

---

## Récapitulatif de votre annonce

@component('mail::panel')
**Titre:** {{ $annonce->title ?? 'Votre annonce' }}

**Type:** {{ $annonce->type ?? 'Non spécifié' }}

**Date de création:** {{ $annonce->created_at ? $annonce->created_at->format('d/m/Y à H:i') : now()->format('d/m/Y à H:i') }}

**Statut:** <span style="color: #f59e0b; font-weight: 600;">En attente de validation</span>
@endcomponent

---

## ⏱️ Prochaines étapes

1. **Validation** - Un administrateur examinera votre annonce dans les plus brefs délais
2. **Notification** - Vous recevrez un email dès que votre annonce sera validée
3. **Publication** - Votre annonce sera visible par tous les utilisateurs de la plateforme

@component('mail::button', ['url' => $frontendUrl, 'color' => 'primary'])
Voir mes annonces
@endcomponent

---

## Statut de votre annonce

| État | Description |
|:-----|:------------|
| **En attente** | En cours de vérification par notre équipe |
| **Validée** | Visible par tous les utilisateurs |
| **Rejetée** | Nécessite des modifications (vous recevrez les détails) |

---

## Conseils pour une validation rapide

- Assurez-vous que vos photos sont de bonne qualité
- Vérifiez que la description est complète et précise
- Confirmez que les coordonnées (localisation) sont exactes

---

## Besoin d'aide ?

Si vous avez des questions concernant votre annonce, n'hésitez pas à nous contacter :

@component('mail::panel')
**Email:** [{{ $supportEmail }}](mailto:{{ $supportEmail }})  
**Site web:** [TunisiaCamp]({{ config('app.url') }})  
**Délai de traitement:** 24-48 heures ouvrées
@endcomponent

---

Nous vous tiendrons informé dès que votre annonce sera validée.

Cordialement,  
**L'équipe TunisiaCamp**

@component('mail::subcopy')
Cette demande de validation expire le {{ $expiresAt->format('d/m/Y à H:i') }}. Passé ce délai, vous devrez soumettre à nouveau votre annonce.
@endcomponent
@endcomponent