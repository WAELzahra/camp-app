<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à jour du statut du compte</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 30px 15px;
            line-height: 1.6;
        }
        .email-container {
            max-width: 550px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: #1a202c;
            padding: 30px 25px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .content {
            padding: 35px 30px;
        }
        .greeting {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 15px;
            font-weight: 500;
        }
        .message {
            color: #4a5568;
            margin-bottom: 25px;
            font-size: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 18px;
            margin: 20px 0;
            text-align: center;
        }
        .status-active {
            background-color: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #48bb78;
        }
        .status-inactive {
            background-color: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #f56565;
        }
        .info-box {
            background-color: #f7fafc;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
            border: 1px solid #e2e8f0;
        }
        .button {
            display: inline-block;
            background-color: #4299e1;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 500;
            margin: 15px 0;
            transition: background-color 0.2s;
        }
        .button:hover {
            background-color: #3182ce;
        }
        .footer {
            background-color: #f7fafc;
            padding: 25px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 13px;
        }
        hr {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 25px 0;
        }
        @media only screen and (max-width: 600px) {
            .content {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>{{ $appName }}</h1>
        </div>
        
        <div class="content">
            <div class="greeting">
                Bonjour {{ $userName }},
            </div>
            
            <div class="message">
                Le statut de votre compte a été modifié par un administrateur.
            </div>
            
            @if($statusValue == 1)
                <div class="status-badge status-active">
                    ✅ COMPTE ACTIVÉ
                </div>
                <div class="message">
                    Vous avez maintenant accès à toutes les fonctionnalités de notre plateforme.
                    Vous pouvez vous connecter en cliquant sur le bouton ci-dessous.
                </div>
                <div style="text-align: center;">
                    <a href="{{ $loginUrl }}" class="button">Se connecter</a>
                </div>
            @else
                <div class="status-badge status-inactive">
                    ❌ COMPTE DÉSACTIVÉ
                </div>
                <div class="message">
                    Votre compte a été désactivé. Pour plus d'informations,
                    veuillez contacter notre équipe de support.
                </div>
            @endif
            
            <hr>
            
            <div class="info-box">
                <strong style="color: #2d3748;">📞 Support client</strong><br>
                <span style="color: #4a5568; font-size: 14px;">
                    Email : <a href="mailto:{{ $supportEmail }}" style="color: #4299e1; text-decoration: none;">{{ $supportEmail }}</a><br>
                    Téléphone : +216 XX XXX XXX
                </span>
            </div>
        </div>
        
        <div class="footer">
            <p style="margin: 0 0 10px 0;">
                © {{ $currentYear }} {{ $appName }}. Tous droits réservés.
            </p>
            <p style="margin: 0; font-size: 12px;">
                Cet email a été envoyé à {{ $userEmail }}<br>
                <a href="{{ url('/desinscription') }}" style="color: #718096;">Se désinscrire</a>
            </p>
        </div>
    </div>
</body>
</html>