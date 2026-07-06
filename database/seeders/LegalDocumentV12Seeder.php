<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Task 5 — publishes version 1.2 of the CGU and CGV:
 *  - removes "payment intermediary" language (replaced by the authorized
 *    tourism intermediary wording) in FR / EN / AR,
 *  - appends the KYC clause to the CGU,
 *  - appends the Agency/Commission engagement modes clause to the CGV.
 *
 * Publishing a new active version automatically triggers the existing
 * re-acceptance flow (LegalConsentService compares user acceptances against
 * active document ids), so every user must re-accept on next login.
 */
class LegalDocumentV12Seeder extends Seeder
{
    private const REPLACEMENTS = [
        // FR
        "Tunisia Camp agit exclusivement en qualité d'intermédiaire de paiement"
            => "Tunisia Camp agit en qualité d'intermédiaire touristique autorisé, facilitant la mise en relation entre campeurs et prestataires de services",
        "intermédiaire de paiement"
            => "intermédiaire touristique autorisé",
        // EN
        "Tunisia Camp acts exclusively as a payment intermediary"
            => "Tunisia Camp acts as an authorized tourism intermediary, facilitating the connection between campers and service providers",
        "payment intermediary"
            => "authorized tourism intermediary",
        // AR
        "وسيطاً في الدفع"
            => "وسيطاً سياحياً معتمداً",
        "وسيط دفع"
            => "وسيط سياحي معتمد",
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->publishNewVersion('cgu', $this->kycClauseFr(), $this->kycClauseEn(), $this->kycClauseAr());
            $this->publishNewVersion('cgv', $this->modesClauseFr(), $this->modesClauseEn(), $this->modesClauseAr());
        });
    }

    private function publishNewVersion(string $type, string $appendFr, string $appendEn, string $appendAr): void
    {
        $current = LegalDocument::where('type', $type)->where('is_active', true)->first();
        if (!$current) {
            $this->command?->warn("No active {$type} document found — skipped.");
            return;
        }
        if (LegalDocument::where('type', $type)->where('version', '1.2')->exists()) {
            $this->command?->info("{$type} v1.2 already exists — skipped.");
            return;
        }

        LegalDocument::where('type', $type)->update(['is_active' => false]);

        LegalDocument::create([
            'type'           => $type,
            'version'        => '1.2',
            'effective_date' => '[EFFECTIVE_DATE]' === '[EFFECTIVE_DATE]' ? now()->toDateString() : '[EFFECTIVE_DATE]',
            'content_fr'     => $this->applyReplacements($current->content_fr) . "\n\n" . $appendFr,
            'content_en'     => $this->applyReplacements($current->content_en) . "\n\n" . $appendEn,
            'content_ar'     => $this->applyReplacements($current->content_ar) . "\n\n" . $appendAr,
            'is_active'      => true,
        ]);

        $this->command?->info("{$type} v1.2 published.");
    }

    private function applyReplacements(?string $content): string
    {
        return str_replace(array_keys(self::REPLACEMENTS), array_values(self::REPLACEMENTS), $content ?? '');
    }

    /* ─────────── CGU: KYC clause ─────────── */

    private function kycClauseFr(): string
    {
        return <<<'MD'
## Vérification d'identité (KYC)

Les comptes non-campeurs (prestataires, guides, fournisseurs d'équipement, organisateurs) doivent soumettre leurs documents d'identité et documents légaux lors de l'inscription. Le compte reste inactif jusqu'à la vérification complète par un administrateur.

Les comptes campeurs doivent compléter la vérification d'identité avant tout retrait de fonds ou toute réservation d'équipement.

Les documents soumis sont stockés de manière chiffrée et ne sont accessibles qu'au personnel autorisé. Tunisia Camp se réserve le droit de rejeter tout compte sans motiver sa décision. Le statut vérifié peut être révoqué si les documents fournis s'avèrent frauduleux.
MD;
    }

    private function kycClauseEn(): string
    {
        return <<<'MD'
## Identity Verification (KYC)

Non-camper accounts (providers, guides, equipment suppliers, organizers) must submit identity and legal documents at registration. The account remains inactive until admin verification is complete.

Camper accounts must complete identity verification before any withdrawal or equipment reservation.

Submitted documents are stored encrypted and are accessible only to authorized personnel. Tunisia Camp reserves the right to reject any account without stating a reason. Verified status may be revoked if documents are found to be fraudulent.
MD;
    }

    private function kycClauseAr(): string
    {
        return <<<'MD'
## التحقق من الهوية (KYC)

يجب على الحسابات غير المخيّمين (مزودو الخدمات، المرشدون، موردو المعدات، المنظمون) تقديم وثائق الهوية والوثائق القانونية عند التسجيل. يبقى الحساب غير نشط حتى اكتمال التحقق من قبل المشرف.

يجب على حسابات المخيّمين إتمام التحقق من الهوية قبل أي عملية سحب أو حجز معدات.

تُخزَّن الوثائق المقدَّمة بشكل مشفَّر ولا يمكن الوصول إليها إلا من قبل الموظفين المخوَّلين. تحتفظ Tunisia Camp بالحق في رفض أي حساب دون إبداء الأسباب. يمكن إلغاء حالة "موثَّق" إذا تبيَّن أن الوثائق المقدَّمة مزوَّرة.
MD;
    }

    /* ─────────── CGV: engagement modes clause ─────────── */

    private function modesClauseFr(): string
    {
        return <<<'MD'
## Modes d'engagement prestataire

Les prestataires collaborent avec Tunisia Camp selon l'un des deux modes suivants :

- **Mode Commission** : le prestataire gère son compte et fixe ses propres prix ; Tunisia Camp déduit une commission sur chaque réservation confirmée.
- **Mode Agence** : Tunisia Camp gère les réservations pour le compte du prestataire à un prix de vente convenu ; le prestataire reçoit le prix de vente diminué de la marge d'agence.

Le mode d'engagement et les taux applicables sont convenus contractuellement. À chaque réservation, le mode et le taux en vigueur sont enregistrés de manière immuable ; les modifications ultérieures ne s'appliquent jamais rétroactivement. Tout changement de mode nécessite l'approbation d'un administrateur.
MD;
    }

    private function modesClauseEn(): string
    {
        return <<<'MD'
## Provider Engagement Modes

Providers work with Tunisia Camp under one of two modes:

- **Commission Mode**: the provider manages their own account and sets their own prices; Tunisia Camp deducts a commission on each confirmed booking.
- **Agency Mode**: Tunisia Camp manages bookings on the provider's behalf at an agreed sale price; the provider receives the sale price minus the agency margin.

The engagement mode and applicable rates are agreed contractually. On every reservation, the mode and rate in force are recorded immutably; later changes never apply retroactively. Any mode change requires admin approval.
MD;
    }

    private function modesClauseAr(): string
    {
        return <<<'MD'
## أنماط تعاقد مزودي الخدمة

يتعامل مزودو الخدمة مع Tunisia Camp وفق أحد النمطين التاليين:

- **نمط العمولة**: يدير مزود الخدمة حسابه ويحدد أسعاره بنفسه؛ وتقتطع Tunisia Camp عمولة عن كل حجز مؤكَّد.
- **نمط الوكالة**: تدير Tunisia Camp الحجوزات نيابة عن مزود الخدمة بسعر بيع متفق عليه؛ ويتلقى مزود الخدمة سعر البيع مخصوماً منه هامش الوكالة.

يُتَّفق على نمط التعاقد والنسب المطبَّقة تعاقدياً. وعند كل حجز، يُسجَّل النمط والنسبة المعمول بهما بشكل غير قابل للتغيير؛ ولا تُطبَّق التعديلات اللاحقة بأثر رجعي أبداً. ويتطلب أي تغيير في النمط موافقة المشرف.
MD;
    }
}
