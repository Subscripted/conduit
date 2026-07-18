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

    public function call(string $endpoint, array $payload): array
    {
        $body = [
            'model' => $payload['model'],
            'instructions' => $payload['instruction'] ?? '',
            'max_input_tokens' => $payload['maxTokens'] ?? 16,
            'input' => $this->buildInput($payload),
            'tools' => !empty($payload['tools']) ? $this->transformTools($payload['tools']) : null,
        ];

        $raw = $this->request('https://api.openai.com/v1/' . $endpoint, $body);

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

    private function transformTools(array $tools): array
    {
        $result = [];

        foreach ($tools as $tool) {
            $result[] = match ($tool['_prism_type']) {
                'web_search' => array_filter([
                    'type' => 'web_search',
                    'search_context_size' => $tool['context_size'] ?? 'medium',
                    'user_location' => $tool['user_location'] ?? null,
                    'filters' => array_filter([
                        'allowed_domains' => $tool['allowed_domains'] ?? null,
                        'blocked_domains' => $tool['blocked_domains'] ?? null,
                    ]) ?: null,
                ]),

                'mcp' => array_filter([
                    'type' => 'mcp',
                    'server_label' => $tool['name'],
                    'server_url' => $tool['url'],
                    'require_approval' => $tool['require_approval'] ?? 'always',
                    'allowed_tools' => $tool['allowed_tools'] ?? null,
                ]),

                'function' => [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'],
                        'parameters' => $tool['parameters'],
                    ],
                ],

                'image_generation' => array_filter([
                    'type' => 'image_generation',
                    'model' => $tool['model'] ?? null,
                    'size' => $tool['size'] ?? null,
                    'quality' => $tool['quality'] ?? null,
                    'background' => $tool['background'] ?? null,
                    'moderation' => $tool['moderation'] ?? null,
                    'input_fidelity' => $tool['input_fidelity'] ?? null,
                    'output_format' => $tool['output_format'] ?? null,
                    'output_compression' => $tool['output_compression'] ?? null,
                    'partial_images' => $tool['partial_images'] ?? null,
                ], fn($v) => $v !== null),

                default => null,
            };
        }

        return array_filter($result);
    }
}