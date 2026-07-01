<?php

namespace adapter;

use AbstractLLMAdapter;

class OpenAIAdapter extends AbstractLLMAdapter
{
    public function __construct(private string $apiKey)
    {
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->apiKey];
    }

    public function call(array $payload): array
    {
        $body = [
            'model' => $payload['model'],
            'instructions' => $payload['instruction'] ?? null,
            'input' => $this->buildInput($payload),
        ];

        $raw = $this->request('https://api.openai.com/v1/responses', $body);

        return [
            'content' => $raw['output'][0]['content'][0]['text'] ?? '',
            'model' => $raw['model'] ?? '',
            'input_tokens' => $raw['usage']['input_tokens'] ?? 0,
            'output_tokens' => $raw['usage']['output_tokens'] ?? 0,
            'errors' => [],
        ];
    }

    private function buildInput(array $payload): array
    {
        $messages = [];

        foreach ($payload['context'] as $message) {
            $messages[] = $message;
        }

        $messages[] = [
            'role' => $payload['user'] ?? 'user',
            'content' => $payload['content'],
        ];

        return $messages;
    }
}