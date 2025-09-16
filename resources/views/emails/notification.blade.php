<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Nouvelle Notification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <style>
        /* Useful for some modern clients — keep rules minimal */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse !important; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        /* Mobile */
        @media screen and (max-width:600px) {
            .container { width: 100% !important; padding: 16px !important; }
            .hero { font-size: 20px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family: Arial, Helvetica, sans-serif; color:#333333;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table class="container" width="600" cellpadding="0" cellspacing="0" role="presentation"
                       style="width:600px; max-width:600px; background-color:#ffffff; border-radius:8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); overflow:hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="padding:20px 24px; background: linear-gradient(90deg,#1c7ed6,#15aabf); color:#fff;">
                            <h1 class="hero" style="margin:0; font-size:22px; line-height:1.1; font-weight:700;">
                                Nouvelle notification
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:20px 24px;">
                            <p style="margin:0 0 12px 0; font-size:16px;">Bonjour <strong>{{ $user->name }}</strong>,</p>

                            <p style="margin:0 0 16px 0; font-size:15px; color:#555;">
                                Vous avez reçu une nouvelle notification :
                            </p>

                            <div style="padding:14px; background-color:#f7fbff; border-left:4px solid #1c7ed6; border-radius:4px; margin-bottom:16px;">
                                <p style="margin:0; font-size:15px; color:#1f2937;"><strong>Message</strong></p>
                                <p style="margin:6px 0 0 0; font-size:15px; color:#333;">{{ $contenu }}</p>
                            </div>

                            <p style="margin:0 0 6px 0; font-size:14px; color:#777;">
                                <strong>Urgence :</strong> <span style="color:#111;">{{ ucfirst($urgence) }}</span>
                            </p>

                            <!-- optional CTA (uncomment if needed)
                            <p style="margin:18px 0 0 0;">
                                <a href="{{ $actionUrl ?? '#' }}" target="_blank"
                                   style="display:inline-block; padding:10px 18px; background:#1c7ed6; color:#fff; text-decoration:none; border-radius:6px; font-weight:600;">
                                    View notification
                                </a>
                            </p>
                            -->

                            <p style="margin:20px 0 0 0; font-size:13px; color:#999;">
                                Merci,<br/>L’équipe
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:12px 24px; background:#fbfbfd; text-align:center; color:#9aa3ad; font-size:12px;">
                            © {{ date('Y') }} Your Company — If you do not want to receive these emails, please update your preferences.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
s