<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Publishes version 1.1 of the privacy policy (confidentialite) with the
 * "Identity documents & personal data" section: encryption at rest,
 * restricted access, retention windows, user rights.
 *
 * Publishing a new active version triggers the existing re-acceptance flow.
 */
class ConfidentialiteV11Seeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $current = LegalDocument::where('type', 'confidentialite')->where('is_active', true)->first();
            if (!$current) {
                $this->command?->warn('No active confidentialite document — skipped.');
                return;
            }
            if (LegalDocument::where('type', 'confidentialite')->where('version', '1.1')->exists()) {
                $this->command?->info('confidentialite v1.1 already exists — skipped.');
                return;
            }

            LegalDocument::where('type', 'confidentialite')->update(['is_active' => false]);

            LegalDocument::create([
                'type'           => 'confidentialite',
                'version'        => '1.1',
                'effective_date' => now()->toDateString(),
                'content_fr'     => $current->content_fr . "\n\n" . $this->sectionFr(),
                'content_en'     => $current->content_en . "\n\n" . $this->sectionEn(),
                'content_ar'     => $current->content_ar . "\n\n" . $this->sectionAr(),
                'is_active'      => true,
            ]);

            $this->command?->info('confidentialite v1.1 published.');
        });
    }

    private function sectionFr(): string
    {
        return <<<'MD'
## Documents d'identité et données personnelles

Dans le cadre de la vérification d'identité (KYC) et des obligations légales, Tunisia Camp collecte certains documents personnels : carte d'identité nationale (CIN), passeport, registre de commerce, patente, certificats professionnels et documents légaux des établissements.

**Chiffrement** : tous ces documents sont chiffrés au repos avant leur stockage. Même en cas d'accès direct au support de stockage, leur contenu est illisible.

**Accès restreint** : seuls les administrateurs et le personnel de vérification autorisés peuvent consulter ces documents, via des accès authentifiés et journalisés. Ils ne sont jamais exposés publiquement ni transmis à des tiers, sauf obligation légale.

**Durée de conservation** :
- Documents refusés lors de la vérification : supprimés automatiquement après 90 jours en l'absence de nouvelle soumission.
- Copie de la CIN attachée aux locations de matériel : supprimée automatiquement 12 mois après la fin de la location.
- Documents actifs (compte vérifié) : conservés pendant la durée de vie du compte, puis supprimés dans les délais légaux applicables.

**Vos droits** : vous pouvez demander la consultation, la mise à jour ou la suppression de vos documents en contactant contact@tunisiacamp.tn. La suppression de documents requis peut entraîner la suspension des fonctionnalités correspondantes (retraits, locations, publication d'offres).
MD;
    }

    private function sectionEn(): string
    {
        return <<<'MD'
## Identity Documents and Personal Data

As part of identity verification (KYC) and legal obligations, Tunisia Camp collects certain personal documents: national ID card (CIN), passport, business registry, patente, professional certificates and establishments' legal documents.

**Encryption**: all these documents are encrypted at rest before storage. Even with direct access to the storage medium, their content is unreadable.

**Restricted access**: only authorized administrators and verification staff can view these documents, through authenticated and logged access. They are never publicly exposed nor shared with third parties, except where legally required.

**Retention periods**:
- Documents rejected during verification: automatically deleted after 90 days if not resubmitted.
- CIN copy attached to equipment rentals: automatically deleted 12 months after the rental ends.
- Active documents (verified account): kept for the lifetime of the account, then deleted within applicable legal timeframes.

**Your rights**: you may request access to, update or deletion of your documents by contacting contact@tunisiacamp.tn. Deleting required documents may suspend the corresponding features (withdrawals, rentals, publishing listings).
MD;
    }

    private function sectionAr(): string
    {
        return <<<'MD'
## وثائق الهوية والبيانات الشخصية

في إطار التحقق من الهوية (KYC) والالتزامات القانونية، تجمع Tunisia Camp بعض الوثائق الشخصية: بطاقة التعريف الوطنية، جواز السفر، السجل التجاري، الباتيندة، الشهادات المهنية والوثائق القانونية للمؤسسات.

**التشفير**: تُشفَّر جميع هذه الوثائق قبل تخزينها. وحتى في حال الوصول المباشر إلى وسيط التخزين، يبقى محتواها غير قابل للقراءة.

**الوصول المقيَّد**: لا يمكن الاطلاع على هذه الوثائق إلا من قبل المشرفين وموظفي التحقق المخوَّلين، عبر وصول موثَّق ومسجَّل. ولا تُعرَض علناً ولا تُشارَك مع أطراف ثالثة إلا بمقتضى القانون.

**مدة الاحتفاظ**:
- الوثائق المرفوضة أثناء التحقق: تُحذف تلقائياً بعد 90 يوماً في غياب إعادة التقديم.
- نسخة بطاقة التعريف المرفقة بتأجير المعدات: تُحذف تلقائياً بعد 12 شهراً من نهاية التأجير.
- الوثائق النشطة (حساب موثَّق): تُحفَظ طيلة مدة الحساب، ثم تُحذف ضمن الآجال القانونية المعمول بها.

**حقوقك**: يمكنك طلب الاطلاع على وثائقك أو تحديثها أو حذفها بمراسلة contact@tunisiacamp.tn. وقد يؤدي حذف الوثائق المطلوبة إلى تعليق الخصائص المرتبطة بها (السحب، التأجير، نشر العروض).
MD;
    }
}
