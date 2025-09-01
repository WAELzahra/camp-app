<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Notification Matériel' }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
            color: #333333;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .header {
            background: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
        }

        .content {
            padding: 30px;
        }

        .content h2 {
            font-size: 18px;
            color: #4CAF50;
            margin-bottom: 10px;
        }

        .content p {
            line-height: 1.6;
            font-size: 15px;
        }

        .materielle-box {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #4CAF50;
            border-radius: 8px;
        }

        .materielle-box strong {
            display: block;
            margin-bottom: 5px;
        }

        .btn {
            display: inline-block;
            padding: 12px 20px;
            margin-top: 20px;
            background-color: #4CAF50;
            color: white !important;
            text-decoration: none;
            font-weight: bold;
            border-radius: 6px;
        }

        .btn:hover {
            background-color: #45a049;
        }

        .footer {
            background: #f1f1f1;
            text-align: center;
            padding: 15px;
            font-size: 13px;
            color: #777777;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $title ?? 'Notification Matériel' }}</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <h2>Bonjour {{ $user->name ?? 'Utilisateur' }},</h2>

            @if(isset($materielle))
                @if($materielle->status == 0)
                    <p>Votre matériel <strong>{{ $materielle->nom }}</strong> est en attente d'activation par l'administrateur.</p>
                @elseif($materielle->status == 1)
                    <p>Bonne nouvelle ! Votre matériel <strong>{{ $materielle->nom }}</strong> a été activé et est maintenant disponible sur la plateforme.</p>
                @elseif($materielle->status == 2)
                    <p>Attention : Votre matériel <strong>{{ $materielle->nom }}</strong> a été désactivé. Veuillez vérifier les informations ou contacter l'administration.</p>
                @else
                    <p>{{ $messageContent ?? 'Voici une mise à jour concernant votre matériel.' }}</p>
                @endif

                <!-- Materiel Info -->
                <div class="materielle-box">
                    <strong>Nom :</strong> {{ $materielle->nom }}
                    <strong>Description :</strong> {{ $materielle->description }}
                    <strong>Tarif par nuit :</strong> {{ $materielle->tarif_nuit }} TND
                    <strong>Quantité disponible :</strong>
                    {{ $materielle->quantite_dispo }}/{{ $materielle->quantite_total }}
                </div>
            @else
                <p>{{ $messageContent ?? 'Voici une notification importante.' }}</p>
            @endif

            <a href="{{ url('/materielles') }}" class="btn">Voir mes matériels</a>
        </div>

        <!-- Footer -->
        <div class="footer">
            © {{ date('Y') }} Camp-App. Tous droits réservés.
        </div>
    </div>
</body>

</html>
