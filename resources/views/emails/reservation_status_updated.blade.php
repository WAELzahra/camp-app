<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reservation Status Update</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: #1a1a2e; padding: 24px 32px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; }
        .body { padding: 32px; color: #333333; }
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-weight: bold; font-size: 14px; margin: 8px 0; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-pending  { background: #fef9c3; color: #92400e; }
        .status-canceled { background: #f3f4f6; color: #374151; }
        .status-other    { background: #e0e7ff; color: #3730a3; }
        .footer { background: #f9fafb; padding: 16px 32px; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Tunisia Camp — Reservation Update</h1>
    </div>
    <div class="body">
        <p>Hello <strong>{{ $recipientName }}</strong>,</p>

        <p>The status of your reservation for <strong>{{ $itemName }}</strong> has been updated.</p>

        @php
            $statusClass = match(true) {
                in_array($reservation->status, ['approved','confirmed','paid','retrieved','confirmée','remboursée_partielle','remboursée_totale']) => 'status-approved',
                in_array($reservation->status, ['rejected','refusée'])                                                                             => 'status-rejected',
                in_array($reservation->status, ['canceled','cancelled_by_camper','cancelled_by_fournisseur','annulée_par_utilisateur','annulée_par_organisateur']) => 'status-canceled',
                in_array($reservation->status, ['pending','en_attente_paiement','en_attente_validation','remboursement_en_attente','disputed'])     => 'status-pending',
                default => 'status-other',
            };
        @endphp

        <p>New status: <span class="status-badge {{ $statusClass }}">{{ $reservation->status }}</span></p>

        @if($oldStatus)
            <p style="color:#6b7280; font-size:13px;">Previous status: {{ $oldStatus }}</p>
        @endif

        <p>If you have any questions, please contact our support team.</p>
        <p>Thank you for choosing Tunisia Camp!</p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} Tunisia Camp. This is an automated notification.
    </div>
</div>
</body>
</html>
