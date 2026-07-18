<?php


namespace adapter;

use AbstractLLMAdapter;

class AnthropicAdapter extends AbstractLLMAdapter
{

    function __construct(private string $apiKey)
    {
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->apiKey];
    }

    public function call(string $endpoint, array $payload): array
    {
        $body = [
            'model' => $payload['model'],
            'maxTokens' => $payload['maxTokens'] ?? 16,
            'system' => $payload['instruction'] ?? '',
        ];

        $raw = $this->request('https://api.antrophic.com/v1/' . $endpoint, $body);


    }
}