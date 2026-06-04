<?php

namespace App\Services\AI\Adapters;

use App\Services\AI\RateLimitService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqAdapter implements LLMAdapterInterface
{
    private const MAX_TOKENS = 2048;

    // Groq free tier limits
    private const LIMIT_MINUTE     = 30;
    private const LIMIT_DAY        = 6000;
    private const WARN_MINUTE      = 24;   // 80 % of minute limit
    private const WARN_DAY         = 4800; // 80 % of day limit
    private const MAX_RETRIES      = 3;

    public function __construct(
        private readonly RateLimitService $rateLimiter,
    ) {}

    public function complete(string $systemPrompt, string $userMessage, int $maxTokens = 1000, array $history = []): string
    {
        $maxTokens = min($maxTokens, self::MAX_TOKENS);

        $this->enforceRateLimits();

        $url = rtrim((string) config('services.groq.base_url'), '/') . '/chat/completions';

        // Build multi-turn messages: system → history → current user message
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $turn) {
            if (isset($turn['role'], $turn['content']) && in_array($turn['role'], ['user', 'assistant'], true)) {
                $messages[] = ['role' => $turn['role'], 'content' => (string) $turn['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            'model'      => config('services.groq.model'),
            'max_tokens' => $maxTokens,
            'messages'   => $messages,
        ];

        $startMs  = (int) round(microtime(true) * 1000);
        $response = $this->callWithRetry($url, $payload);
        $elapsed  = (int) round(microtime(true) * 1000) - $startMs;

        $this->rateLimiter->increment('groq:rate:minute', 60);
        $this->rateLimiter->increment('groq:rate:day',    86400);
        $this->warnIfApproachingLimits();

        $content = $response['choices'][0]['message']['content'] ?? null;

        if (! is_string($content) || $content === '') {
            throw new \RuntimeException('Unexpected Groq response structure');
        }

        Log::info('groq_api_call', [
            'user_id'          => auth()->id(),
            'tokens_requested' => $maxTokens,
            'prompt_length'    => strlen($systemPrompt . $userMessage),
            'response_time_ms' => $elapsed,
            'cached'           => false,
        ]);

        return $content;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function enforceRateLimits(): void
    {
        if (! $this->rateLimiter->checkLimit('groq:rate:minute', self::LIMIT_MINUTE, 60)) {
            throw new \RuntimeException('Groq rate limit exceeded: minute');
        }

        if (! $this->rateLimiter->checkLimit('groq:rate:day', self::LIMIT_DAY, 86400)) {
            throw new \RuntimeException('Groq rate limit exceeded: day');
        }
    }

    private function callWithRetry(string $url, array $payload): array
    {
        $retries = 0;
        $delays  = [1, 2, 4]; // seconds — exponential backoff

        while (true) {
            try {
                $response = Http::withToken((string) config('services.groq.key'))
                    ->timeout(30)
                    ->post($url, $payload);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Retry on transient network failures (DNS, timeout, SSL)
                if ($retries < self::MAX_RETRIES) {
                    sleep($delays[$retries]);
                    $retries++;
                    continue;
                }
                throw new \RuntimeException('AI_SERVICE_UNAVAILABLE');
            }

            if ($response->status() === 429 && $retries < self::MAX_RETRIES) {
                sleep($delays[$retries]);
                $retries++;
                continue;
            }

            if ($response->status() === 429) {
                throw new \RuntimeException('AI_RATE_LIMITED');
            }

            if (! $response->successful()) {
                throw new \RuntimeException(
                    "Groq API error {$response->status()}: {$response->body()}"
                );
            }

            return $response->json();
        }
    }

    private function warnIfApproachingLimits(): void
    {
        $usage = $this->rateLimiter->getUsage();

        if ($usage['minute'] > self::WARN_MINUTE) {
            Log::warning('groq_rate_warning', ['window' => 'minute', 'used' => $usage['minute'], 'limit' => self::LIMIT_MINUTE]);
        }

        if ($usage['day'] > self::WARN_DAY) {
            Log::warning('groq_rate_warning', ['window' => 'day', 'used' => $usage['day'], 'limit' => self::LIMIT_DAY]);
        }
    }
}
