<?php

namespace adapter;

use AbstractLLMAdapter;

class AnthropicAdapter extends AbstractLLMAdapter
{
    private const BASE_URL          = 'https://api.anthropic.com/v1';
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(private readonly string $sApiKey) {}

    protected function headers(): array
    {
        return [
            'x-api-key'         => $this->sApiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
        ];
    }

    public function chat(array $aPayload): array
    {
        $aBody = [
            'model'      => $aPayload['model'],
            'max_tokens' => $aPayload['maxTokens'] ?? 1024,
            'messages'   => $this->buildMessages($aPayload),
        ];

        if (!empty($aPayload['instruction'])) {
            $aBody['system'] = $aPayload['instruction'];
        }
        if (!empty($aPayload['tools'])) {
            $aBuiltTools = $this->buildTools($aPayload['tools']);
            if (!empty($aBuiltTools)) {
                $aBody['tools'] = $aBuiltTools;
            }
        }

        $aRaw = $this->request(self::BASE_URL . '/messages', $aBody);
        return $this->normalizeResponse($aRaw);
    }

    // ── Input builder ─────────────────────────────────────────────────────

    private function buildMessages(array $aPayload): array
    {
        $aMessages = [];

        foreach ($aPayload['context'] ?? [] as $aMessage) {
            $aMessages[] = $this->transformContextMessage($aMessage);
        }

        $aMessages[] = [
            'role'    => $aPayload['user'] ?? 'user',
            'content' => $this->transformContent($aPayload['content'] ?? []),
        ];

        return $aMessages;
    }

    private function transformContextMessage(array $aMessage): array
    {
        if ($aMessage['role'] === 'tool_result') {
            $mContent = $aMessage['content'];
            return [
                'role'    => 'user',
                'content' => [[
                    'type'        => 'tool_result',
                    'tool_use_id' => $aMessage['tool_call_id'],
                    'content'     => is_array($mContent) ? $mContent : (string) $mContent,
                ]],
            ];
        }

        $mContent = $aMessage['content'];
        return [
            'role'    => $aMessage['role'],
            'content' => is_string($mContent)
                ? $mContent
                : $this->transformContent($this->wrapIfSingleBlock($mContent)),
        ];
    }

    private function wrapIfSingleBlock(array $aContent): array
    {
        return array_is_list($aContent) ? $aContent : [$aContent];
    }

    private function transformContent(array $aContent): array
    {
        $aResult = [];
        foreach ($aContent as $aBlock) {
            switch ($aBlock['type'] ?? '') {
                case 'text':
                    $aResult[] = ['type' => 'text', 'text' => $aBlock['text']];
                    break;
                case 'image':
                    if (isset($aBlock['url'])) {
                        $aResult[] = ['type' => 'image', 'source' => ['type' => 'url', 'url' => $aBlock['url']]];
                    } else {
                        $aResult[] = ['type' => 'image', 'source' => [
                            'type'       => 'base64',
                            'media_type' => $aBlock['media_type'] ?? 'image/jpeg',
                            'data'       => $aBlock['data'],
                        ]];
                    }
                    break;
                case 'file':
                    if (isset($aBlock['url'])) {
                        $aResult[] = ['type' => 'document', 'source' => ['type' => 'url', 'url' => $aBlock['url']]];
                    } else {
                        $aResult[] = ['type' => 'document', 'source' => [
                            'type'       => 'base64',
                            'media_type' => $aBlock['media_type'] ?? 'application/pdf',
                            'data'       => $aBlock['data'],
                        ]];
                    }
                    break;
                default:
                    $aResult[] = $aBlock;
            }
        }
        return $aResult;
    }

    // ── Tool builder ──────────────────────────────────────────────────────

    private function buildTools(array $aTools): array
    {
        $aResult = [];
        foreach ($aTools as $aTool) {
            $sType  = $aTool['_type'] ?? '';
            $aBuilt = null;

            switch ($sType) {
                case 'web_search':
                    $aBuilt = ['type' => 'web_search_20250305', 'name' => 'web_search'];
                    if (!empty($aTool['max_uses'])) $aBuilt['max_uses'] = $aTool['max_uses'];
                    break;

                case 'function':
                    $aBuilt = [
                        'name'         => $aTool['name'],
                        'description'  => $aTool['description'],
                        'input_schema' => $aTool['parameters'],
                    ];
                    break;

                // image_generation and mcp are not supported by Anthropic — skip silently
            }

            if ($aBuilt !== null) {
                $aResult[] = $aBuilt;
            }
        }
        return $aResult;
    }

    // ── Response normalizer ───────────────────────────────────────────────

    private function normalizeResponse(array $aRaw): array
    {
        $aOutputs = [];

        foreach ($aRaw['content'] ?? [] as $aBlock) {
            switch ($aBlock['type'] ?? '') {
                case 'text':
                    $aOutputs[] = [
                        'type'        => 'text',
                        'text'        => $aBlock['text'] ?? '',
                        'annotations' => $this->normalizeCitations($aBlock['citations'] ?? []),
                    ];
                    break;

                case 'tool_use':
                    if ($aBlock['name'] === 'web_search') {
                        $aOutputs[] = [
                            'type'      => 'web_search',
                            'status'    => 'completed',
                            'call_id'   => $aBlock['id'] ?? '',
                            'arguments' => json_encode($aBlock['input'] ?? []),
                        ];
                    } else {
                        $aOutputs[] = [
                            'type'      => 'function_call',
                            'name'      => $aBlock['name'] ?? '',
                            'call_id'   => $aBlock['id'] ?? '',
                            'arguments' => json_encode($aBlock['input'] ?? []),
                        ];
                    }
                    break;

                case 'thinking':
                    $aOutputs[] = ['type' => 'thinking', 'thinking' => $aBlock['thinking'] ?? ''];
                    break;
            }
        }

        return [
            'model'         => $aRaw['model'] ?? '',
            'input_tokens'  => $aRaw['usage']['input_tokens'] ?? 0,
            'output_tokens' => $aRaw['usage']['output_tokens'] ?? 0,
            'outputs'       => $aOutputs,
            'errors'        => [],
        ];
    }

    private function normalizeCitations(array $aCitations): array
    {
        $aResult = [];
        foreach ($aCitations as $aCitation) {
            $aResult[] = [
                'type'       => 'url_citation',
                'url'        => $aCitation['url'] ?? '',
                'title'      => $aCitation['title'] ?? '',
                'cited_text' => $aCitation['cited_text'] ?? '',
            ];
        }
        return $aResult;
    }
}
