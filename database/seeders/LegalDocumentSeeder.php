<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Seeds the initial set of legal documents (v1.0).
 *
 * Safe to run multiple times — skips types that already have an active version.
 *
 * After seeding, ALL existing users will see the acceptance modal on their next
 * page load because no user_contract_acceptances rows exist yet.
 *
 * To publish a new version later:
 *   php artisan legal:publish-version cgu 2.0
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->documents() as $doc) {
            if (LegalDocument::active()->ofType($doc['type'])->exists()) {
                $this->command->info("Skipping '{$doc['type']}' — active version already exists.");
                continue;
            }
            LegalDocument::create($doc);
            $this->command->info("Seeded {$doc['type']} v{$doc['version']}.");
        }
    }

    private function documents(): array
    {
        return [
            [
                'type'           => 'cgu',
                'version'        => '1.0',
                'effective_date' => '2026-06-27',
                'content_fr'     => $this->cguFr(),
                'content_en'     => $this->cguEn(),
                'content_ar'     => $this->cguAr(),
            ],
            [
                'type'           => 'cgv',
                'version'        => '1.0',
                'effective_date' => '2026-06-27',
                'content_fr'     => $this->cgvFr(),
                'content_en'     => $this->cgvEn(),
                'content_ar'     => $this->cgvAr(),
            ],
            [
                'type'           => 'mentions_legales',
                'version'        => '1.0',
                'effective_date' => '2026-06-27',
                'content_fr'     => "Tunisia Camp est une plateforme de mise en relation entre campeurs et prestataires en Tunisie. Le Credit de Reservation constitue une avance sur services — non un instrument de monnaie electronique (Loi 2016-48). Paiements traites par Flouci et ClicToPay/BH Bank.",
                'content_en'     => "Tunisia Camp is a platform connecting campers and providers in Tunisia. Reservation Credit constitutes a service advance — not an electronic money instrument (Law 2016-48). Payments processed by Flouci and ClicToPay/BH Bank.",
                'content_ar'     => "Tunisia Camp منصة ربط بين المخيمين والمزودين في تونس. رصيد الحجز دفعة مسبقة على الخدمات وليس اداة نقود الكترونية (القانون 2016-48). تتم المدفوعات عبر Flouci وClicToPay/بنك الاسكان.",
            ],
            [
                'type'           => 'confidentialite',
                'version'        => '1.0',
                'effective_date' => '2026-06-27',
                'content_fr'     => "Tunisia Camp collecte uniquement les donnees necessaires au service (nom, email, telephone, reservations). Ces donnees ne sont pas vendues. Vous disposez d'un droit d'acces, de rectification et de suppression conformement a la loi 2004-63.",
                'content_en'     => "Tunisia Camp collects only data necessary for the service (name, email, phone, bookings). This data is not sold. You have the right to access, correct and delete your data under Law No. 2004-63.",
                'content_ar'     => "تجمع Tunisia Camp البيانات الضرورية فقط (الاسم والبريد والهاتف والحجوزات). لا تباع هذه البيانات. يحق لك الوصول اليها وتصحيحها وحذفها وفق القانون 2004-63.",
            ],
        ];
    }

    // ── CGU ───────────────────────────────────────────────────────────────────

    private function cguFr(): string
    {
        return "CONDITIONS GENERALES D'UTILISATION — Tunisia Camp v1.0 (27/06/2026)\n\n"
            . "1. OBJET\nTunisia Camp est une plateforme de mise en relation entre campeurs, centres de camping, guides et fournisseurs en Tunisie. L'utilisation vaut acceptation des presentes CGU.\n\n"
            . "2. INSCRIPTION\nReservee aux personnes majeures (18 ans+). Vous etes responsable de la confidentialite de vos identifiants.\n\n"
            . "3. CREDIT DE RESERVATION\nBon de service prepaye — non un depot bancaire ni une monnaie electronique. Non remboursable en especes, non transferable sauf fermeture de compte ou erreur de la plateforme. Aucun interet applique.\n\n"
            . "4. CONTENU ET COMPORTEMENT\nPas de contenu illicite ou diffamatoire. Tunisia Camp peut supprimer tout contenu non conforme.\n\n"
            . "5. PROPRIETE INTELLECTUELLE\nTous les contenus sont la propriete de Tunisia Camp ou de ses partenaires.\n\n"
            . "6. RESPONSABILITE\nTunisia Camp est un intermediaire et n'est pas responsable de l'execution des prestations par les prestataires.\n\n"
            . "7. DROIT APPLICABLE\nDroit tunisien — tribunaux tunisiens competents.";
    }

    private function cguEn(): string
    {
        return "TERMS OF SERVICE — Tunisia Camp v1.0 (27/06/2026)\n\n"
            . "1. PURPOSE\nTunisia Camp connects campers, camping centres, guides and suppliers in Tunisia. Use constitutes acceptance of these Terms.\n\n"
            . "2. REGISTRATION\nOpen to adults (18+). You are responsible for your credential security.\n\n"
            . "3. RESERVATION CREDIT\nPrepaid service voucher — not a bank deposit or electronic money. Non-refundable in cash, non-transferable except on account closure or platform error. No interest accrues.\n\n"
            . "4. CONTENT AND CONDUCT\nNo unlawful or defamatory content. Tunisia Camp may remove non-compliant content.\n\n"
            . "5. INTELLECTUAL PROPERTY\nAll content belongs to Tunisia Camp or its partners.\n\n"
            . "6. LIABILITY\nTunisia Camp is an intermediary and is not liable for provider service delivery.\n\n"
            . "7. GOVERNING LAW\nTunisian law — Tunisian courts have jurisdiction.";
    }

    private function cguAr(): string
    {
        return "شروط الاستخدام — Tunisia Camp الاصدار 1.0 (27/06/2026)\n\n"
            . "1. الغرض\nTunisia Camp منصة للربط بين المخيمين ومراكز التخييم والمرشدين والموردين في تونس.\n\n"
            . "2. التسجيل\nللبالغين (18 سنة فاكثر). انت مسؤول عن سرية بيانات دخولك.\n\n"
            . "3. رصيد الحجز\nقسيمة خدمة مدفوعة مسبقا — ليس وديعة بنكية ولا نقودا الكترونية. غير قابل للاسترداد نقدا وغير قابل للتحويل.\n\n"
            . "4. المحتوى والسلوك\nلا محتوى مخالفا للقانون. تحتفظ Tunisia Camp بحق حذف المحتوى المخالف.\n\n"
            . "5. القانون المطبق\nالقانون التونسي — المحاكم التونسية مختصة.";
    }

    // ── CGV ───────────────────────────────────────────────────────────────────

    private function cgvFr(): string
    {
        return "CONDITIONS GENERALES DE VENTE — Tunisia Camp v1.0 (27/06/2026)\n\n"
            . "1. CHAMP D'APPLICATION\nS'applique a toute reservation via Tunisia Camp : sejours, locations de materiel, evenements.\n\n"
            . "2. PRIX ET PAIEMENT\nPrix en DT (TND) TTC. Paiement via Flouci, ClicToPay/BH Bank ou Credit de Reservation. Commission de service prelevee par Tunisia Camp.\n\n"
            . "3. PAIEMENT VIA CREDIT DE RESERVATION\nConstitue une avance sur services. Paiements traites par Flouci et ClicToPay/BH Bank.\n\n"
            . "4. CONFIRMATION ET ANNULATION\nReservation confirmee a reception du paiement. Conditions d'annulation definies par la politique du prestataire.\n\n"
            . "5. RESPONSABILITE\nLe prestataire est seul responsable de la prestation. Tunisia Camp est un intermediaire.\n\n"
            . "6. LITIGES\nContact : contact@tunisiacamp.tn — tribunaux tunisiens competents.";
    }

    private function cgvEn(): string
    {
        return "GENERAL TERMS OF SALE — Tunisia Camp v1.0 (27/06/2026)\n\n"
            . "1. SCOPE\nApplies to all bookings via Tunisia Camp: stays, equipment rentals, events.\n\n"
            . "2. PRICES AND PAYMENT\nPrices in TND inclusive of taxes. Payment via Flouci, ClicToPay/BH Bank or Reservation Credit. Service commission charged by Tunisia Camp.\n\n"
            . "3. RESERVATION CREDIT PAYMENT\nConstitutes a service advance. Payments processed by Flouci and ClicToPay/BH Bank.\n\n"
            . "4. CONFIRMATION AND CANCELLATION\nBooking confirmed on payment receipt. Cancellation conditions set by provider policy.\n\n"
            . "5. LIABILITY\nProvider is solely responsible for service delivery. Tunisia Camp is an intermediary.\n\n"
            . "6. DISPUTES\nContact: contact@tunisiacamp.tn — Tunisian courts have jurisdiction.";
    }

    private function cgvAr(): string
    {
        return "الشروط العامة للبيع — Tunisia Camp الاصدار 1.0 (27/06/2026)\n\n"
            . "1. النطاق\nتسري على كل حجز عبر Tunisia Camp: الاقامة والتاجير والفعاليات.\n\n"
            . "2. الاسعار والدفع\nبالدينار التونسي شاملة الضرائب. الدفع عبر Flouci او ClicToPay/بنك الاسكان او رصيد الحجز.\n\n"
            . "3. الدفع عبر رصيد الحجز\nيعد دفعة مسبقة على الخدمات.\n\n"
            . "4. التاكيد والالغاء\nيتم تاكيد الحجز عند استلام الدفع. شروط الالغاء محددة بسياسة المزود.\n\n"
            . "5. المسؤولية\nالمزود مسؤول وحده عن الخدمة. Tunisia Camp وسيط فحسب.\n\n"
            . "6. النزاعات\nللتواصل: contact@tunisiacamp.tn — المحاكم التونسية مختصة.";
    }
}
