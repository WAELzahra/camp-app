<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

/**
 * Retrieves relevant platform knowledge for a user question
 * by embedding the question and searching Qdrant for similar topics.
 */
class PlatformKnowledgeService
{
    private string $qdrantUrl  = 'http://localhost:6333';
    private string $ollamaUrl  = 'http://localhost:11434';
    private string $collection = 'platform_knowledge';

    /**
     * Get relevant platform context for a user question.
     * Returns the top 5 most relevant knowledge chunks as a string.
     */
    public function getRelevantContext(string $question, int $topK = 5): string
    {
        $embedding = $this->embed($question);

        $results = $this->searchQdrant($embedding, $topK);

        if (empty($results)) {
            return $this->getFallbackContext();
        }

        return collect($results)
            ->map(fn($r) => $r['text'])
            ->implode("\n\n");
    }

    /**
     * Get embedding vector from Ollama (local, free).
     */
    private function embed(string $text): array
    {
        $response = Http::timeout(30)->post("{$this->ollamaUrl}/api/embeddings", [
            'model'  => 'bge-m3',
            'prompt' => $text,
        ]);

        return $response->json('embedding');
    }

    /**
     * Search Qdrant for similar topics.
     */
    private function searchQdrant(array $vector, int $topK): array
    {
        $response = Http::timeout(10)->post(
            "{$this->qdrantUrl}/collections/{$this->collection}/points/search",
            [
                'vector'       => $vector,
                'limit'        => $topK,
                'with_payload' => true,
            ]
        );

        $result = $response->json('result');

        if (!is_array($result)) {
            return [];
        }

        return array_map(fn($r) => [
            'id'    => $r['payload']['original_id'] ?? $r['id'],
            'text'  => $r['payload']['text'] ?? '',
            'score' => round($r['score'], 3),
        ], $result);
    }

    /**
     * Fallback context when no relevant topics found.
     */
    private function getFallbackContext(): string
    {
        return "TunisiaCamp is a camping platform for Tunisia. "
            . "Features include: camping zone discovery, partner centre booking, "
            . "gear rental, group events, AI trip planning, and wallet payments. "
            . "Contact support@tunisiacamp.com for help.";
    }
}