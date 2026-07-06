<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        .header { border-bottom: 2px solid #2F5D3A; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { color: #2F5D3A; font-size: 20px; margin: 0; }
        .meta { margin-bottom: 20px; }
        .meta td { padding: 2px 8px 2px 0; vertical-align: top; }
        table.items { width: 100%; border-collapse: collapse; margin: 16px 0; }
        table.items th { background: #2F5D3A; color: #fff; padding: 6px 8px; text-align: left; font-size: 11px; }
        table.items td { border-bottom: 1px solid #ddd; padding: 6px 8px; }
        .totals { width: 45%; margin-left: 55%; border-collapse: collapse; }
        .totals td { padding: 4px 8px; }
        .totals .grand { font-weight: bold; font-size: 14px; border-top: 2px solid #2F5D3A; }
        .footer { margin-top: 30px; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 8px; }
        .voided { color: #b91c1c; font-size: 28px; font-weight: bold; text-align: center;
                  border: 3px solid #b91c1c; padding: 8px; margin-bottom: 16px; }
    </style>
</head>
<body>
    @if ($invoice->isVoided())
        <div class="voided">FACTURE ANNULÉE — {{ $invoice->voided_at->format('d/m/Y') }}</div>
    @endif

    <div class="header">
        <h1>{{ $invoice->invoice_type === 'sale' ? 'FACTURE DE VENTE' : 'FACTURE DE COMMISSION' }}</h1>
        <p>N° {{ $invoice->invoice_number }} — émise le {{ $invoice->issued_at->format('d/m/Y') }}</p>
    </div>

    <table class="meta" width="100%">
        <tr>
            <td width="50%">
                <strong>Émetteur</strong><br>
                {{ $invoice->issuer_entity }}<br>
                Matricule fiscal : {{ $invoice->issuer_fiscal_id }}<br>
                Licence : [LICENSE_NUMBER]<br>
                Adresse : [PLATFORM_ADDRESS]
            </td>
            <td width="50%">
                <strong>Client</strong><br>
                {{ $invoice->client_name }}<br>
                @if ($invoice->client_fiscal_id)
                    Matricule fiscal : {{ $invoice->client_fiscal_id }}<br>
                @endif
                @if ($invoice->payment_method)
                    Mode de paiement : {{ $invoice->payment_method }}
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr><th>Désignation</th><th style="text-align:right">Montant HT</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    @if ($invoice->invoice_type === 'sale')
                        Prestation de séjour / réservation (réf. {{ $invoice->reservation_type }} #{{ $invoice->reservation_id }})
                        — vendue par Tunisia Camp
                    @else
                        Commission de mise en relation (réf. {{ $invoice->reservation_type }} #{{ $invoice->reservation_id }})
                        — la prestation principale est fournie par le prestataire
                    @endif
                </td>
                <td style="text-align:right">{{ number_format($invoice->amount_ht, 3, ',', ' ') }} TND</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Total HT</td><td style="text-align:right">{{ number_format($invoice->amount_ht, 3, ',', ' ') }} TND</td></tr>
        <tr><td>TVA ({{ number_format($invoice->tva_rate, 0) }}%)</td><td style="text-align:right">{{ number_format($invoice->tva_amount, 3, ',', ' ') }} TND</td></tr>
        @if ($invoice->timbre_fiscal > 0)
            <tr><td>Timbre fiscal</td><td style="text-align:right">{{ number_format($invoice->timbre_fiscal, 3, ',', ' ') }} TND</td></tr>
        @endif
        <tr class="grand"><td>Total TTC</td><td style="text-align:right">{{ number_format($invoice->amount_ttc, 3, ',', ' ') }} TND</td></tr>
    </table>

    <div class="footer">
        {{ $invoice->issuer_entity }} — Matricule fiscal {{ $invoice->issuer_fiscal_id }} — [PLATFORM_ADDRESS]<br>
        Document généré électroniquement par Tunisia Camp. Facture n° {{ $invoice->invoice_number }}.
    </div>
</body>
</html>
