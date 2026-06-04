<?php

namespace App\Services\AI\Adapters;

interface LLMAdapterInterface
{
    /**
     * Send a prompt to the LLM and return the text completion.
     *
     * @param  string  $systemPrompt  Instructions that define the assistant's role and output format.
     * @param  string  $userMessage   The user's request combined with any injected context.
     * @param  int     $maxTokens     Upper bound on tokens in the response.
     * @return string  Raw text content returned by the model.
     */
    /**
     * @param  array  $history  Prior turns: [['role'=>'user'|'assistant','content'=>string], ...]
     */
    public function complete(string $systemPrompt, string $userMessage, int $maxTokens = 1000, array $history = []): string;
}
