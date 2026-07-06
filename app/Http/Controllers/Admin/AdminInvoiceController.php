<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    /** GET /admin/invoices */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->latest('issued_at');

        if ($request->filled('type'))   $query->where('invoice_type', $request->input('type'));
        if ($request->filled('search')) $query->where(fn ($q) => $q
            ->where('invoice_number', 'LIKE', '%' . $request->input('search') . '%')
            ->orWhere('client_name', 'LIKE', '%' . $request->input('search') . '%'));

        return response()->json(['success' => true, 'data' => $query->paginate($request->integer('per_page', 20))]);
    }

    /** POST /admin/invoices — manual generation for a reservation. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_type'     => 'required|in:sale,commission',
            'reservation_id'   => 'required|integer',
            'reservation_type' => 'required|in:centre,event,materielle',
            'client_name'      => 'required|string|max:255',
            'client_fiscal_id' => 'nullable|string|max:100',
            'amount_ttc'       => 'required|numeric|min:0.01',
            'payment_method'   => 'nullable|string|max:50',
            'apply_timbre'     => 'nullable|boolean',
        ]);

        $invoice = $this->invoices->create(
            type:            $validated['invoice_type'],
            reservationId:   $validated['reservation_id'],
            reservationType: $validated['reservation_type'],
            clientName:      $validated['client_name'],
            amountTtcBase:   (float) $validated['amount_ttc'],
            clientFiscalId:  $validated['client_fiscal_id'] ?? null,
            paymentMethod:   $validated['payment_method'] ?? null,
            actingUserId:    $request->user()->id,
            applyTimbre:     $request->boolean('apply_timbre', true),
        );

        return response()->json(['success' => true, 'data' => $invoice], 201);
    }

    /** GET /admin/invoices/{invoice}/pdf */
    public function downloadPdf(Request $request, Invoice $invoice)
    {
        $content = $this->invoices->downloadPdf($invoice, $request->user()->id);

        return response($content)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $invoice->invoice_number . '.pdf"');
    }

    /** POST /admin/invoices/{invoice}/void */
    public function void(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:255']);

        $this->invoices->void($invoice, $request->user()->id, $validated['reason']);

        return response()->json(['success' => true, 'message' => 'Facture annulée (enregistrement conservé).']);
    }
}
