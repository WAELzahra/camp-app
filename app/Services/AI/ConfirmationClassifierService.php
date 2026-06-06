<?php

namespace App\Services\AI;

use App\Services\AI\Adapters\LLMAdapterInterface;
use Illuminate\Support\Facades\Log;

/**
 * Classifies the user's intent toward a previously shown recommendation.
 *
 * Returns one of: "confirm" | "modify" | "reject" | "other"
 *
 * One focused LLM call, max 50 tokens.
 * Never throws — any failure returns "other" (safe, passes through to pipeline).
 */
class ConfirmationClassifierService
{
    public function __construct(
        private readonly LLMAdapterInterface $llm,
    ) {}

    /**
     * Classify the user's message relative to a shown recommendation.
     *
     * @param string $message            The current user message.
     * @param array  $conversationHistory Last turns for context (optional).
     * @return string "confirm" | "modify" | "reject" | "other"
     */
    public function classify(
        string $message,
        array  $conversationHistory = [],
    ): string {
        try {
            $raw    = $this->llm->complete(
                $this->systemPrompt(),
                $this->userPayload($message, $conversationHistory),
                50,
                $conversationHistory,
            );

            $result = mb_strtolower(trim($raw ?? ''));
            // Strip anything that is not a-z (markdown, punctuation, whitespace)
            $result = preg_replace('/[^a-z]/', '', $result) ?? '';

            if (in_array($result, ['confirm', 'modify', 'reject', 'other'], true)) {
                return $result;
            }

            // Partial match — LLM may have returned "confirmed" etc.
            foreach (['confirm', 'modify', 'reject'] as $word) {
                if (str_starts_with($result, $word)) {
                    return $word;
                }
            }

            Log::warning('confirmation_classifier_unexpected_output', [
                'raw'     => $raw,
                'cleaned' => $result,
            ]);

            return 'other';

        } catch (\Throwable $e) {
            Log::warning('confirmation_classifier_failed', [
                'error' => $e->getMessage(),
            ]);

            return 'other';
        }
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function systemPrompt(): string
    {
        return <<<PROMPT
LANGUAGE RULE (highest priority): The output word must always be one of: confirm, modify, reject, other — in English. But any success or rejection messages triggered downstream must use the same language as the user's message.

You are a classifier for a Tunisian camping assistant chatbot.

The assistant has just shown the user a camping recommendation (zone or centre).
Classify the user's NEXT message into EXACTLY one category.

CATEGORIES:
- confirm   : user accepts the recommendation and wants to book it
- modify    : user wants to change something (destination, budget, dates, group size, zone type)
- reject    : user does not want this recommendation at all
- other     : question about the place, unrelated topic, or genuinely ambiguous

EXAMPLES — CONFIRM:
  French : "oui parfait", "ça me convient", "c'est bon", "allons-y", "réserve ça",
           "oui je veux ça", "c'est parfait pour nous", "ok go", "oui, book it"
  English: "yes book it", "perfect", "let's do it", "sounds good", "yes please"
  Arabic : "نعم احجز", "موافق", "حجزلي"
  Dialect: "yalla", "واه مزيانة", "barra nabda", "khlass ana mwafaq"

EXAMPLES — MODIFY:
  French : "je préfère la côte", "c'est trop cher", "peut-on changer les dates",
           "je veux plutôt un centre", "je préfère une autre région",
           "mais je veux plus de nuits", "trop loin pour moi",
           "enlève le matériel", "je préfère Bizerte"
  English: "can we do 3 nights instead", "too expensive", "I prefer the coast",
           "change the destination", "can you find something cheaper"
  Arabic : "أريد منطقة أخرى", "غالي جداً", "أريد بالقرب من البحر"
  Dialect: "trop cher baddel", "ma3jebniich el-zona", "w'chbih proche Tunis"

EXAMPLES — REJECT:
  French : "non merci", "pas vraiment", "je préfère autre chose",
           "pas ce qu'il me faut", "ça ne m'intéresse pas", "non"
  English: "no thanks", "not interested", "no that's not for me"
  Arabic : "لا شكراً", "لا أريد هذا"
  Dialect: "ma3jebniich", "la merci", "non barra"

EXAMPLES — OTHER:
  "c'est quoi le terrain ?", "est-ce qu'il y a de l'eau ?",
  "tell me more about the zone", "what activities are available ?",
  "كم يبعد عن تونس ؟", "des infos sur la sécurité ?"

Respond with ONLY one word: confirm, modify, reject, or other.
No explanation. No punctuation. No markdown. Just the single word.
PROMPT;
    }

    private function userPayload(string $message, array $history): string
    {
        $context = '';
        if (! empty($history)) {
            $recent = array_slice($history, -4);
            $turns  = array_map(
                fn ($h) => ($h['role'] ?? '') . ': ' . mb_substr($h['content'] ?? '', 0, 200),
                $recent,
            );
            $context = "Recent conversation:\n" . implode("\n", $turns) . "\n\n";
        }

        return $context . 'User message to classify: ' . $message;
    }
}
