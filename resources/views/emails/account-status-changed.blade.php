// resources/views/emails/account-status-changed.blade.php
<!DOCTYPE html>
<html>
<head>
    <title>Changement de statut de votre compte</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        .content {
            padding: 20px;
        }
        .status {
            font-size: 18px;
            font-weight: bold;
            padding: 10px;
            text-align: center;
            border-radius: 5px;
            margin: 20px 0;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ $appName }}</h2>
        </div>
        
        <div class="content">
            <h3>Bonjour {{ $userName }},</h3>
            
            <p>Le statut de votre compte a été modifié par un administrateur.</p>
            
            <div class="status {{ $statusValue == 1 ? 'status-active' : 'status-inactive' }}">
                Votre compte est maintenant <strong>{{ $status }}</strong>
            </div>
            
            @if($statusValue == 1)
                <p>Vous pouvez maintenant vous connecter et utiliser pleinement toutes les fonctionnalités de notre plateforme.</p>
                <p style="text-align: center;">
                    <a href="{{ $loginUrl }}" class="button">Se connecter</a>
                </p>
            @else
                <p>Pour plus d'informations, veuillez contacter l'équipe d'administration.</p>
            @endif
            
            <p>Cordialement,<br>L'équipe {{ $appName }}</p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $appName }}. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>