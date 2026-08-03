<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use App\Services\LegalConsentService;
use Illuminate\Database\Seeder;

/**
 * Publishes version 1.4 of the 'cgv' and 'cgu' legal documents — a
 * substantive content change, unlike V13's wording-only republish.
 *
 * Legal counsel required TunisiaCamp's legal documents to:
 *  - stop describing TunisiaCamp as a "payment intermediary" and stop
 *    claiming it "does not hold customer funds" (inaccurate: card/online
 *    payments settle into TunisiaCamp's own merchant bank account);
 *  - describe the real settlement flow instead: ClicToPay/BH Bank process
 *    the payment, funds settle into TunisiaCamp's merchant account,
 *    TunisiaCamp then remits each Provider under the Provider Agreement
 *    after deducting its commission;
 *  - add explicit Provider payment-collection authorization language;
 *  - avoid escrow/trust-account/payment-institution characterizations not
 *    supported by TunisiaCamp's actual (unlicensed) status under Law
 *    No. 2016-48, while not asserting an unsupported legal conclusion that
 *    the activity is definitely exempt from that law either.
 *
 * Mirrors the rewrite already applied to the public pages in the frontend
 * repo (src/pages/(public)/Legal/CGVPage.tsx and
 * src/components/home/TermsContent.tsx) — this is the condensed
 * consent-summary shown at signup/acceptance, not a verbatim copy.
 *
 * mentions_legales / confidentialite got lighter terminology-consistency
 * edits only (no legal-conclusion changes), so they are not republished
 * here — republishing them would just be version churn with no real
 * content diff for consent purposes.
 */
class LegalDocumentV14Seeder extends Seeder
{
    private const VERSION = '1.4';

    public function run(): void
    {
        foreach (['cgv', 'cgu'] as $type) {
            $current = LegalDocument::where('type', $type)->where('is_active', true)->first();
            if (!$current) {
                $this->command?->warn("No active {$type} document found — skipped.");
                continue;
            }
            if (LegalDocument::where('type', $type)->where('version', self::VERSION)->exists()) {
                $this->command?->info("{$type} v" . self::VERSION . ' already exists — skipped.');
                continue;
            }

            LegalConsentService::publishVersion(
                type: $type,
                version: self::VERSION,
                effectiveDate: now()->toDateString(),
                contentFr: $type === 'cgv' ? $this->cgvFr() : $this->cguFr(),
                contentEn: $type === 'cgv' ? $this->cgvEn() : $this->cguEn(),
                contentAr: $type === 'cgv' ? $this->cgvAr() : $this->cguAr(),
            );

            $this->command?->info("{$type} v" . self::VERSION . ' published.');
        }
    }

    /* ─────────── CGV ─────────── */

    private function cgvFr(): string
    {
        return "CONDITIONS GENERALES DE VENTE — Tunisia Camp v1.4 (" . now()->format('d/m/Y') . ")\n\n"
            . "1. CHAMP D'APPLICATION\nS'applique a toute reservation via Tunisia Camp : sejours, locations de materiel, evenements.\n\n"
            . "2. ROLE DE TUNISIA CAMP\nTunisia Camp est une plateforme numerique de reservation touristique mettant en relation campeurs et Prestataires. Tunisia Camp n'est pas une banque et n'est pas agreee en qualite d'etablissement de paiement au sens de la Loi n 2016-48 du 11 juillet 2016. Tunisia Camp ne fournit aucun service de transfert de fonds entre particuliers.\n\n"
            . "3. MECANISME DE PAIEMENT\nLes paiements sont traites par des prestataires de paiement agrees (ClicToPay/BH Bank) et regles sur le compte bancaire marchand de Tunisia Camp. Tunisia Camp reverse ensuite chaque Prestataire, apres deduction de sa commission, conformement au Contrat Prestataire conclu avec lui.\n\n"
            . "4. AUTORISATION DU PRESTATAIRE\nEn concluant un Contrat Prestataire avec Tunisia Camp, chaque Prestataire autorise Tunisia Camp a encaisser pour son compte les paiements des campeurs relatifs aux reservations effectuees via la plateforme, a deduire la commission convenue, et a lui reverser le solde selon le calendrier convenu.\n\n"
            . "5. CREDIT DE RESERVATION\nLe Credit de Reservation est un credit de service prepaye a usage restreint, utilisable uniquement pour des reservations sur Tunisia Camp. Il ne constitue ni un depot, ni un compte de paiement, ni de la monnaie electronique.\n\n"
            . "6. CONFIRMATION ET ANNULATION\nReservation confirmee a reception du paiement. En cas d'annulation, le remboursement est prefere sous forme de Credit de Reservation, sauf obligation legale de remboursement en especes.\n\n"
            . "7. LITIGES\nContact : contact@tunisiacamp.tn — tribunaux tunisiens competents.";
    }

    private function cgvEn(): string
    {
        return "GENERAL TERMS OF SALE — Tunisia Camp v1.4 (" . now()->format('d/m/Y') . ")\n\n"
            . "1. SCOPE\nApplies to all bookings via Tunisia Camp: stays, equipment rentals, events.\n\n"
            . "2. TUNISIA CAMP'S ROLE\nTunisia Camp is a digital tourism booking platform connecting campers and Providers. Tunisia Camp is not a bank and is not licensed as a payment institution under Law No. 2016-48 of 11 July 2016. Tunisia Camp does not provide money remittance between individuals.\n\n"
            . "3. PAYMENT MECHANISM\nPayments are processed by licensed payment providers (ClicToPay/BH Bank) and settle into Tunisia Camp's merchant bank account. Tunisia Camp then remits each Provider, after deducting its commission, under the Provider Agreement between them.\n\n"
            . "4. PROVIDER AUTHORIZATION\nBy entering into a Provider Agreement with Tunisia Camp, each Provider authorizes Tunisia Camp to collect camper payments relating to bookings made through the platform on its behalf, to deduct the agreed commission, and to remit the balance per the agreed schedule.\n\n"
            . "5. RESERVATION CREDIT\nThe Reservation Credit is a restricted, prepaid service credit usable only for bookings on Tunisia Camp. It is not a deposit, a payment account, or electronic money.\n\n"
            . "6. CONFIRMATION AND CANCELLATION\nBooking confirmed on payment receipt. In case of cancellation, refunds are preferentially credited as Reservation Credit, except where a cash refund is legally required.\n\n"
            . "7. DISPUTES\nContact: contact@tunisiacamp.tn — Tunisian courts have jurisdiction.";
    }

    private function cgvAr(): string
    {
        return "الشروط العامة للبيع — Tunisia Camp الاصدار 1.4 (" . now()->format('d/m/Y') . ")\n\n"
            . "1. النطاق\nتسري على كل حجز عبر Tunisia Camp: الاقامة والتاجير والفعاليات.\n\n"
            . "2. دور Tunisia Camp\nTunisia Camp منصة رقمية للحجوزات السياحية تربط بين المخيمين ومقدمي الخدمات. وهي ليست بنكا وليست مرخصة كمؤسسة دفع بموجب القانون عدد 48 لسنة 2016. ولا تقدم Tunisia Camp أي خدمة لتحويل الأموال بين الأفراد.\n\n"
            . "3. آلية الدفع\nتعالج المدفوعات من قبل مزودي دفع مرخصين (ClicToPay/بنك الإسكان) وتسوى في الحساب البنكي التجاري لـ Tunisia Camp. وتقوم Tunisia Camp لاحقا بتحويل مستحقات كل مقدم خدمة، بعد خصم عمولتها، وفقا لعقد مقدم الخدمة المبرم معه.\n\n"
            . "4. تفويض مقدم الخدمة\nبإبرام عقد مقدم خدمة مع Tunisia Camp، يفوض مقدم الخدمة Tunisia Camp بتحصيل مدفوعات المخيمين المتعلقة بالحجوزات لحسابه، وخصم العمولة المتفق عليها، وتحويل الرصيد وفق الجدول المتفق عليه.\n\n"
            . "5. رصيد الحجز\nرصيد الحجز هو رصيد خدمة مدفوع مسبقا ذو استخدام مقيد، لا يستخدم إلا لحجوزات Tunisia Camp. وهو ليس وديعة ولا حساب دفع ولا نقدا إلكترونيا.\n\n"
            . "6. التأكيد والإلغاء\nيتم تأكيد الحجز عند استلام الدفع. في حالة الإلغاء، يفضل أن يقيد الاسترداد كرصيد حجز، ما لم يفرض القانون استردادا نقديا.\n\n"
            . "7. النزاعات\nللتواصل: contact@tunisiacamp.tn — المحاكم التونسية مختصة.";
    }

    /* ─────────── CGU (booking/payment clause only) ─────────── */

    private function cguFr(): string
    {
        return "CONDITIONS D'UTILISATION — Tunisia Camp v1.4 (" . now()->format('d/m/Y') . ")\n\n"
            . "RESERVATIONS ET PAIEMENTS\nLes paiements sont traites par des prestataires de paiement agrees (ClicToPay/BH Bank) et regles sur le compte bancaire marchand de Tunisia Camp, dans le cadre de son activite de plateforme numerique de reservation touristique. Tunisia Camp reverse ensuite le Prestataire concerne, apres deduction de sa commission, conformement au Contrat Prestataire. Voir les CGV pour le detail complet du mecanisme de paiement.\n\n"
            . "CREDIT DE RESERVATION\nLe Credit de Reservation est un credit de service prepaye a usage restreint, non transferable, utilisable uniquement pour des reservations sur Tunisia Camp — il ne constitue ni un depot bancaire, ni de la monnaie electronique, ni un instrument financier.";
    }

    private function cguEn(): string
    {
        return "TERMS OF USE — Tunisia Camp v1.4 (" . now()->format('d/m/Y') . ")\n\n"
            . "BOOKINGS AND PAYMENTS\nPayments are processed by licensed payment providers (ClicToPay/BH Bank) and settle into Tunisia Camp's merchant bank account, as part of its digital tourism booking platform activity. Tunisia Camp then remits the relevant Provider, after deducting its commission, under the Provider Agreement. See the CGV for the full payment mechanism.\n\n"
            . "RESERVATION CREDIT\nThe Reservation Credit is a restricted, non-transferable, prepaid service credit usable only for bookings on Tunisia Camp — it is not a bank deposit, electronic money, or a financial instrument.";
    }

    private function cguAr(): string
    {
        return "شروط الاستخدام — Tunisia Camp الاصدار 1.4 (" . now()->format('d/m/Y') . ")\n\n"
            . "الحجوزات والمدفوعات\nتعالج المدفوعات من قبل مزودي دفع مرخصين (ClicToPay/بنك الإسكان) وتسوى في الحساب البنكي التجاري لـ Tunisia Camp، في إطار نشاطها كمنصة رقمية للحجوزات السياحية. وتقوم Tunisia Camp لاحقا بتحويل مستحقات مقدم الخدمة المعني، بعد خصم عمولتها، وفقا لعقد مقدم الخدمة. راجع الشروط العامة للبيع لمزيد من التفاصيل حول آلية الدفع.\n\n"
            . "رصيد الحجز\nرصيد الحجز هو رصيد خدمة مدفوع مسبقا ذو استخدام مقيد وغير قابل للتحويل، لا يستخدم إلا لحجوزات Tunisia Camp — وهو ليس وديعة بنكية ولا نقدا إلكترونيا ولا أداة مالية.";
    }
}
