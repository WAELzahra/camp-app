<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP client for the Python ai-research FastAPI service.
 *
 * When config('ai.decision_backend') === 'python', the AI services route their
 * decisions here (recommendation, pricing, safety, group matching) instead of
 * computing them in PHP. Every call is short-timeout and non-throwing: on any
 * failure it returns null so the caller transparently falls back to the PHP
 * rule engine. This keeps the platform resilient if the model service is down.
 */
class AiInferenceClient
{
    public function enabled(): bool
    {
        return config('ai.decision_backend') === 'python';
    }

    private function post(string $path, array $payload): ?array
    {
        try {
            $resp = Http::timeout((int) config('ai.inference_timeout', 3))
                ->withHeaders(['X-AI-Key' => (string) config('ai.inference_key', '')])
                ->acceptJson()
                ->post(rtrim((string) config('ai.inference_url'), '/') . $path, $payload);

            if ($resp->successful()) {
                return $resp->json();
            }
            Log::warning('ai_inference_non_2xx', ['path' => $path, 'status' => $resp->status()]);
        } catch (\Throwable $e) {
            Log::warning('ai_inference_unreachable', ['path' => $path, 'error' => $e->getMessage()]);
        }
        return null;
    }

    /** @return array{label:string,score:int,probabilities:array,model:string}|null */
    public function safety(array $payload): ?array
    {
        return $this->post('/predict/safety', $payload);
    }

    /** @return array{suggested_optimal:float,suggested_min:float,suggested_max:float,price_direction:string,model:string}|null */
    public function pricing(array $payload): ?array
    {
        return $this->post('/predict/pricing', $payload);
    }

    /** @return array{items:array,method:string}|null */
    public function recommendZones(array $profile, int $topK = 5): ?array
    {
        return $this->post('/recommend/zones', ['profile' => $profile, 'top_k' => $topK]);
    }

    /** @return array{cluster_id:int,cluster_members:array,method:string}|null */
    public function matchGroups(array $profile): ?array
    {
        return $this->post('/match/groups', ['profile' => $profile]);
    }
}
