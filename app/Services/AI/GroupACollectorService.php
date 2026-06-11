<?php

namespace App\Services\AI;

/**
 * Single responsibility: determine which Group A fields are still missing
 * and build the appropriate clarification response, OR apply a structured
 * modal submission directly to the conversation state.
 *
 * Group A fields:
 *   destination, duration_nights, group_size, accommodation_type, wants_gear
 *
 * Returns null from check() when all Group A fields are present — the caller
 * may then proceed to the recommendation pipeline immediately.
 */
class GroupACollectorService
{
    public function __construct(
        private readonly ConversationStateService $conversationState,
    ) {}

    /**
     * Inspect $state and return either a clarification payload or null.
     *
     * Rule 1: destination null → destination_clarification with quick_replies.
     * Rule 2: destination known, other Group A fields missing → group_a_modal
     *         containing only the missing fields. wants_gear is always included
     *         unless already non-null in state (meaning it was stated explicitly
     *         in this turn and already merged).
     * Rule 4: all Group A fields complete → return null (run the pipeline).
     */
    public function check(array $state, string $originalMessage = ''): ?array
    {
        $lang = $this->detectLanguage($originalMessage);

        if ($state['destination'] === null) {
            $chatMessage = match ($lang) {
                'en'    => 'Where in Tunisia would you like to camp?',
                'ar'    => 'أين تريد التخييم في تونس؟',
                default => 'Où souhaitez-vous camper en Tunisie ?',
            };
            return [
                'type'         => 'destination_clarification',
                'chat_message' => $chatMessage,
                'quick_replies' => [
                    [
                        'label' => '🌿 Nord — Jendouba / Tabarka',
                        'value' => 'Je veux camper dans le nord, Jendouba ou Tabarka',
                    ],
                    [
                        'label' => '🏙️ Grand Tunis',
                        'value' => 'Je veux camper près de Tunis',
                    ],
                    [
                        'label' => '🌊 Côte Est — Sousse / Nabeul',
                        'value' => 'Je veux camper sur la côte est, Sousse ou Nabeul',
                    ],
                    [
                        'label' => '🏛️ Centre — Kairouan / Sfax',
                        'value' => 'Je veux camper dans le centre, Kairouan ou Sfax',
                    ],
                    [
                        'label' => '🏜️ Sud — Tozeur / Djerba',
                        'value' => 'Je veux camper dans le sud, Tozeur ou Djerba',
                    ],
                ],
            ];
        }

        $missing = $this->missingFields($state);

        if (empty($missing)) {
            return null;
        }

        $destination = $state['destination'];

        $modalMessage = match ($lang) {
            'en'    => "To plan your stay in {$destination}:",
            'ar'    => "لتخطيط إقامتك في {$destination}:",
            default => "Pour planifier votre séjour à {$destination} :",
        };

        // Always append start_date as an optional field if not yet set.
        $dateField = $this->buildField('start_date');
        $fields    = array_values(array_map([$this, 'buildField'], $missing));
        $fields[]  = $dateField;

        return [
            'type'         => 'group_a_modal',
            'chat_message' => $modalMessage,
            'fields'       => $fields,
        ];
    }

    // ── Language detection ────────────────────────────────────────────────────

    private function detectLanguage(string $message): string
    {
        if ($message === '') {
            return 'fr';
        }

        // Arabic script is unambiguous
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $message)) {
            return 'ar';
        }

        $lower = mb_strtolower($message);
        $englishSignals = [
            'i want', 'i would', 'camping', 'where', 'how',
            'can you', 'please', 'looking for', 'recommend',
            'changed my mind', 'prefer',
        ];
        foreach ($englishSignals as $signal) {
            if (str_contains($lower, $signal)) {
                return 'en';
            }
        }

        return 'fr';
    }

    /**
     * Apply a structured modal submission to the conversation state.
     *
     * Validates and normalises field values — never throws.
     * Returns the updated state.
     */
    public function applyModalSubmission(int $userId, array $formData): array
    {
        $delta = [
            'group_size'         => $this->parseInt($formData['group_size'] ?? null, 1, 50),
            'duration_nights'    => $this->parseInt($formData['duration_nights'] ?? null, 1, 30),
            'accommodation_type' => in_array($formData['accommodation_type'] ?? null, ['zone', 'centre'], true)
                                        ? $formData['accommodation_type']
                                        : null,
            'wants_gear'         => $this->parseBool($formData['wants_gear'] ?? null),
            'start_date'         => $this->parseDate($formData['start_date'] ?? null),
        ];

        return $this->conversationState->merge($userId, $delta);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Returns the IDs of Group A fields that are still missing in $state. */
    private function missingFields(array $state): array
    {
        $missing = [];

        if ($state['group_size'] === null) {
            $missing[] = 'group_size';
        }

        if ($state['duration_nights'] === null) {
            $missing[] = 'duration_nights';
        }

        if (! in_array($state['accommodation_type'] ?? 'any', ['zone', 'centre'], true)) {
            $missing[] = 'accommodation_type';
        }

        // wants_gear is always included unless the user explicitly stated it
        // in the current turn (in which case it is already non-null in state).
        if ($state['wants_gear'] === null) {
            $missing[] = 'wants_gear';
        }

        return $missing;
    }

    private function buildField(string $field): array
    {
        return match ($field) {
            'group_size' => [
                'id'      => 'group_size',
                'label'   => 'Combien de personnes ?',
                'type'    => 'number',
                'min'     => 1,
                'max'     => 50,
                'default' => null,
            ],
            'duration_nights' => [
                'id'      => 'duration_nights',
                'label'   => 'Combien de nuits ?',
                'type'    => 'number',
                'min'     => 1,
                'max'     => 30,
                'default' => null,
            ],
            'accommodation_type' => [
                'id'      => 'accommodation_type',
                'label'   => 'Type de séjour',
                'type'    => 'select',
                'options' => [
                    ['label' => '🌿 Zone naturelle',    'value' => 'zone'],
                    ['label' => '🏕️ Centre de camping', 'value' => 'centre'],
                ],
            ],
            'wants_gear' => [
                'id'      => 'wants_gear',
                'label'   => 'Équipements',
                'type'    => 'select',
                'options' => [
                    ['label' => '✅ Oui, recommande-moi du matériel', 'value' => 'true'],
                    ['label' => '🎒 J\'apporte mon propre matériel',  'value' => 'false'],
                ],
            ],
            'start_date' => [
                'id'       => 'start_date',
                'label'    => 'Date de début (optionnel)',
                'type'     => 'date',
                'default'  => '',
                'optional' => true,
            ],
            default => [],
        };
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $str = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            return $str;
        }
        return null;
    }

    private function parseInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return ($int >= $min && $int <= $max) ? $int : null;
    }

    private function parseBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        $str = strtolower((string) $value);
        if ($str === 'true' || $str === '1') {
            return true;
        }
        if ($str === 'false' || $str === '0') {
            return false;
        }
        return null;
    }
}
