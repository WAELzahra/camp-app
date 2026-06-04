<?php

namespace App\Services\AI\Adapters;

class MockAdapter implements LLMAdapterInterface
{
    public function complete(string $systemPrompt, string $userMessage, int $maxTokens = 1000, array $history = []): string
    {
        // Simulate realistic API latency so callers can time the response.
        usleep(500_000); // 500 ms

        // Greeting fast-path — plain text response
        if (str_contains($systemPrompt, "TunisiaCamp's AI camping assistant")) {
            return "Bonjour ! Je suis l'assistant camping TunisiaCamp. Je peux vous aider à planifier un voyage, trouver une zone en Tunisie, ou recommander du matériel en location. Comment puis-je vous aider ?";
        }

        // Unified chat/plan response — inspect only the user's actual question, not the full context
        if (str_contains($systemPrompt, '"type": "chat"')) {
            // Extract the first line after "USER REQUEST:" — that's the real user message
            $actualQuestion = '';
            if (preg_match('/USER REQUEST:\s*(.+)/i', $userMessage, $m)) {
                $actualQuestion = trim($m[1]);
            } else {
                $actualQuestion = trim(strtok($userMessage, "\n"));
            }
            $lower = mb_strtolower($actualQuestion);
            $planningKw = ['camp', 'zone', 'voyage', 'trip', 'tente', 'tent', 'gear',
                'équipement', 'randonnée', 'météo', 'weather', 'tarif', 'prix', 'price',
                'forêt', 'montagne', 'désert', 'plage', 'activit', 'recommend', 'plan',
                'réserver', 'book', 'safety', 'sécurité'];
            $needsPlan = false;
            foreach ($planningKw as $kw) {
                if (str_contains($lower, $kw)) { $needsPlan = true; break; }
            }
            if (! $needsPlan) {
                return json_encode([
                    'type'    => 'chat',
                    'message' => "Je suis l'assistant TunisiaCamp. Posez-moi vos questions sur les zones de camping, le matériel disponible en location, ou la planification de voyages en Tunisie !",
                ], JSON_UNESCAPED_UNICODE);
            }
        }

        // Explainability — returns JSON with why + factors
        if (str_contains($systemPrompt, 'explainability assistant')) {
            return json_encode([
                'why'     => 'Cette recommandation correspond à votre profil et vos préférences de camping.',
                'factors' => [
                    "Niveau d'expérience adapté à la zone",
                    "Budget cohérent avec les tarifs de la catégorie",
                    "Activités disponibles correspondent à vos préférences",
                ],
            ], JSON_UNESCAPED_UNICODE);
        }

        // Pricing advisor — returns plain text, not JSON
        if (str_contains($systemPrompt, 'pricing advisor')) {
            return 'La demande actuelle est modérée pour cette période. Votre prix est bien positionné par rapport au marché tunisien. Nous vous recommandons de maintenir votre tarif actuel tout en surveillant l\'évolution des réservations sur les 7 prochains jours.';
        }

        return json_encode([
            'intent' => [
                'destination'     => 'Ain Draham',
                'budget'          => 'moderate',
                'group_size'      => 4,
                'duration_nights' => 3,
                'trip_style'      => 'forest camping',
            ],
            'recommended_zone' => [
                'id'     => 1,
                'nom'    => 'Forêt de Ain Draham',
                'region' => 'Jendouba',
                'why'    => 'Dense cork-oak forest, mild temperatures, and easy access roads make it ideal for your group size and skill level.',
            ],
            'gear_list' => [
                [
                    'id'        => 1,
                    'nom'       => 'Tente 4 personnes',
                    'brand'     => 'Quechua',
                    'tarif_nuit'=> 25,
                    'url'       => '/marketplace/materielle/1',
                    'reason'    => 'Spacious 4-person tent perfect for your group.',
                ],
                [
                    'id'        => 2,
                    'nom'       => 'Sac de couchage',
                    'brand'     => 'Decathlon',
                    'tarif_nuit'=> 8,
                    'url'       => '/marketplace/materielle/2',
                    'reason'    => 'Rated to 5 °C — ideal for cool forest nights.',
                ],
                [
                    'id'        => 3,
                    'nom'       => 'Réchaud camping',
                    'brand'     => 'MSR',
                    'tarif_nuit'=> 5,
                    'url'       => '/marketplace/materielle/3',
                    'reason'    => 'Lightweight stove for shared cooking.',
                ],
            ],
            'weather_warning'  => null,
            'estimated_cost'   => [
                'gear_per_night'  => 38,
                'total_estimate'  => 114,
                'currency'        => 'TND',
            ],
            'ai_summary' => "Votre aventure à Tabarka s'annonce mémorable ! Les sentiers forestiers d'Aïn Draham offrent des panoramas exceptionnels pour votre groupe. Pour affiner mes prochaines recommandations, quel est votre niveau en camping ? (débutant / intermédiaire / avancé / expert)",
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
