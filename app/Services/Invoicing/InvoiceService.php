<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\InvoiceAuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Task A-03 — invoice creation + PDF generation + audit trail.
 *
 * Two schemas:
 *  - sale (agency mode): full sale price invoiced by Tunisia Camp to the camper.
 *  - commission (commission mode): only the commission amount is invoiced;
 *    the main service is provided by the provider.
 */
class InvoiceService
{
    private const TVA_RATE      = 19.00;
    private const TIMBRE_FISCAL = 0.600;

    /**
     * Create an invoice from a TTC-inclusive base amount.
     *
     * @param string $type            'sale' | 'commission'
     * @param float  $amountTtcBase   amount including TVA, excluding timbre
     */
    public function create(
        string $type,
        int $reservationId,
        string $reservationType,
        string $clientName,
        float $amountTtcBase,
        ?string $clientFiscalId = null,
        ?string $paymentMethod = null,
        ?int $actingUserId = null,
        bool $applyTimbre = true,
    ): Invoice {
        return DB::transaction(function () use (
            $type, $reservationId, $reservationType, $clientName,
            $amountTtcBase, $clientFiscalId, $paymentMethod, $actingUserId, $applyTimbre
        ) {
            $amountHt  = round($amountTtcBase / (1 + self::TVA_RATE / 100), 2);
            $tvaAmount = round($amountTtcBase - $amountHt, 2);
            $timbre    = $applyTimbre ? self::TIMBRE_FISCAL : 0.0;

            $invoice = Invoice::create([
                'invoice_number'   => InvoiceNumberGenerator::next(),
                'invoice_type'     => $type,
                'reservation_id'   => $reservationId,
                'reservation_type' => $reservationType,
                'issuer_entity'    => '[FRIEND_FULL_NAME]',
                'issuer_fiscal_id' => '[FISCAL_ID]',
                'client_name'      => $clientName,
                'client_fiscal_id' => $clientFiscalId,
                'amount_ht'        => $amountHt,
                'tva_rate'         => self::TVA_RATE,
                'tva_amount'       => $tvaAmount,
                'timbre_fiscal'    => $timbre,
                'amount_ttc'       => round($amountTtcBase + $timbre, 2),
                'payment_method'   => $paymentMethod,
                'issued_at'        => now(),
            ]);

            $this->generatePdf($invoice);
            $this->audit($invoice->id, $actingUserId, 'generated', $invoice->invoice_number);

            return $invoice;
        });
    }

    /** Render the PDF and store it encrypted on the private disk. */
    public function generatePdf(Invoice $invoice): void
    {
        $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice]);

        $path = 'invoices/' . $invoice->issued_at->format('Y') . '/' . $invoice->invoice_number . '.pdf.enc';
        // Default disk = R2 in production; contents are encrypted at rest.
        Storage::put($path, Crypt::encrypt($pdf->output()));

        $invoice->update(['pdf_path' => $path]);
    }

    /** Decrypt the stored PDF for an authorized download (audited). */
    public function downloadPdf(Invoice $invoice, ?int $actingUserId = null): string
    {
        abort_if(!$invoice->pdf_path || !Storage::exists($invoice->pdf_path), 404, 'PDF introuvable.');

        $this->audit($invoice->id, $actingUserId, 'downloaded');

        return Crypt::decrypt(Storage::get($invoice->pdf_path));
    }

    /** Invoices are never deleted — only voided, keeping the full record. */
    public function void(Invoice $invoice, int $adminId, string $reason): Invoice
    {
        abort_if($invoice->isVoided(), 422, 'Facture déjà annulée.');

        $invoice->update([
            'voided_at'   => now(),
            'voided_by'   => $adminId,
            'void_reason' => $reason,
        ]);
        $this->audit($invoice->id, $adminId, 'voided', $reason);

        return $invoice;
    }

    private function audit(int $invoiceId, ?int $userId, string $action, ?string $details = null): void
    {
        InvoiceAuditLog::create([
            'invoice_id' => $invoiceId,
            'user_id'    => $userId,
            'action'     => $action,
            'details'    => $details,
            'created_at' => now(),
        ]);
    }
}
