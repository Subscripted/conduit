<?php

namespace adapter;

use AbstractLLMAdapter;

/**
 * Adapter for the Anthropic Messages API (Claude).
 *
 * https://docs.anthropic.com/en/api/messages
 *
 * Translates the provider-neutral payload into the body of the Anthropic
 * Messages API and the response back into the normalized array that
 * ChatResponse is built from. The rest of the application therefore only
 * knows the neutral format and no Anthropic specifics.
 *
 * Anthropic does not support image generation — image() therefore always throws.
 */
class AnthropicAdapter extends AbstractLLMAdapter
{
    private const string BASE_URL = 'https://api.anthropic.com/v1';
    private const string ANTHROPIC_VERSION = '2023-06-01';

    /**
     * @param string $sApiKey API key of the Anthropic account.
     */
    public function __construct(private readonly string $sApiKey)
    {
    }

    /**
     * Provider-specific headers for every request.
     *
     * @return array Header name => value.
     */
    protected function headers(): array
    {
        return [
            'x-api-key' => $this->sApiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
        ];
    }

    /**
     * Image generation — not offered by Anthropic.
     *
     * @param array $aPayload Not evaluated.
     * @return array Never returns.
     * @throws \RuntimeException Always, because the provider has no image endpoint.
     */
    public function image(array $aPayload): array
    {
        throw new \RuntimeException('Image generation is not supported by Anthropic.');
    }

    /**
     * Sends a chat request to the Messages API.
     *
     * @param array $aPayload Neutral payload: model, maxTokens, instruction, context,
     *                        content, tools, jsonSchema, effort, effortSummary.
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors.
     * @throws \RuntimeException If the HTTP request or the API fails.
     */
    public function chat(array $aPayload): array
    {
        $aBody = [
            'model' => $aPayload['model'],
            'max_tokens' => $aPayload['maxTokens'] ?? 1024,
            'messages' => $this->buildMessages($aPayload),
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
        if (!empty($aPayload['jsonSchema'])) {
            $aBody['output_config']['format'] = [
                'type' => 'json_schema',
                'schema' => $aPayload['jsonSchema'],
            ];
        }
        if (!empty($aPayload['effort'])) {
            $aBody['output_config']['effort'] = $aPayload['effort'];
            $aBody['thinking'] = ['type' => 'adaptive'];
            if (!empty($aPayload['effortSummary'])) {
                $aBody['thinking']['display'] = 'summarized';
            }
        }

        $aRaw = $this->request(self::BASE_URL . '/messages', $aBody);
        return $this->normalizeResponse($aRaw);
    }

    // ── Input builder ─────────────────────────────────────────────────────

    /**
     * Builds the messages list from the conversation history plus the
     * current user input. The final message is always sent as role 'user'.
     *
     * @param array $aPayload Neutral payload with context and content.
     * @return array List of messages with role and content.
     */
    private function buildMessages(array $aPayload): array
    {
        $aMessages = [];

        foreach ($aPayload['context'] ?? [] as $aMessage) {
            $aMessages[] = $this->transformContextMessage($aMessage);
        }

        $aMessages[] = [
            'role' => 'user',
            'content' => $this->transformContent($aPayload['content'] ?? []),
        ];

        return $aMessages;
    }

    /**
     * Translates a message from the conversation history into the Anthropic
     * format. Tool-call results run as a user message with a tool_result
     * block, not as a role of their own.
     *
     * @param array $aMessage Message with role, content and, for tool results, tool_call_id.
     * @return array Message in the Anthropic format.
     */
    private function transformContextMessage(array $aMessage): array
    {
        if ($aMessage['role'] === 'tool_result') {
            $mContent = $aMessage['content'];
            return [
                'role' => 'user',
                'content' => [[
                    'type' => 'tool_result',
                    'tool_use_id' => $aMessage['tool_call_id'],
                    'content' => is_array($mContent) ? $mContent : (string)$mContent,
                ]],
            ];
        }

        $mContent = $aMessage['content'];
        return [
            'role' => $aMessage['role'],
            'content' => is_string($mContent)
                ? $mContent
                : $this->transformContent($this->wrapIfSingleBlock($mContent)),
        ];
    }

    /**
     * Wraps a single content block in a list so transformContent() can
     * iterate over blocks uniformly.
     *
     * @param array $aContent A single block or already a list of blocks.
     * @return array List of blocks.
     */
    private function wrapIfSingleBlock(array $aContent): array
    {
        return array_is_list($aContent) ? $aContent : [$aContent];
    }

    /**
     * Translates the neutral content blocks into Anthropic blocks. Files are
     * called 'document' there; images and files come as a url or a base64
     * source depending on their origin. Unknown types are passed through
     * unchanged.
     *
     * @param array $aContent Blocks from Content::text(), ::image() and ::file().
     * @return array Blocks in the Anthropic format.
     */
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
                            'type' => 'base64',
                            'media_type' => $aBlock['media_type'] ?? 'image/jpeg',
                            'data' => $aBlock['data'],
                        ]];
                    }
                    break;
                case 'file':
                    if (isset($aBlock['url'])) {
                        $aResult[] = ['type' => 'document', 'source' => ['type' => 'url', 'url' => $aBlock['url']]];
                    } else {
                        $aResult[] = ['type' => 'document', 'source' => [
                            'type' => 'base64',
                            'media_type' => $aBlock['media_type'] ?? 'application/pdf',
                            'data' => $aBlock['data'],
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

    /**
     * Translates the neutral tool definitions into the Anthropic format.
     * The built-in tools carry a dated type tag (e.g. web_search_20250305).
     * Tools Anthropic does not know (image generation, MCP) are skipped silently.
     *
     * @param array $aTools Tool definitions from Tool::webSearch(), ::webFetch(), ::function().
     * @return array Tools in the Anthropic format, empty when none is supported.
     */
    private function buildTools(array $aTools): array
    {
        $aResult = [];
        foreach ($aTools as $aTool) {
            $sType = $aTool['_type'] ?? '';
            $aBuilt = null;

            switch ($sType) {
                case 'web_search':
                    $aBuilt = ['type' => 'web_search_20250305', 'name' => 'web_search'];
                    if (!empty($aTool['max_uses']))        $aBuilt['max_uses']        = $aTool['max_uses'];
                    if (!empty($aTool['allowed_domains'])) $aBuilt['allowed_domains'] = $aTool['allowed_domains'];
                    if (!empty($aTool['blocked_domains'])) $aBuilt['blocked_domains'] = $aTool['blocked_domains'];
                    break;

                case 'web_fetch':
                    $aBuilt = ['type' => 'web_fetch_20250910', 'name' => 'web_fetch'];
                    if (!empty($aTool['max_uses']))        $aBuilt['max_uses']        = $aTool['max_uses'];
                    if (!empty($aTool['allowed_domains'])) $aBuilt['allowed_domains'] = $aTool['allowed_domains'];
                    if (!empty($aTool['blocked_domains'])) $aBuilt['blocked_domains'] = $aTool['blocked_domains'];
                    if (isset($aTool['citations']))        $aBuilt['citations']       = ['enabled' => (bool)$aTool['citations']];
                    if (!empty($aTool['max_content_tokens'])) $aBuilt['max_content_tokens'] = $aTool['max_content_tokens'];
                    break;

                case 'function':
                    $aBuilt = [
                        'name' => $aTool['name'],
                        'description' => $aTool['description'],
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

    /**
     * Translates the raw API answer into the neutral format for ChatResponse.
     * Anthropic delivers web searches as server_tool_use or tool_use
     * depending on the model — both land on the same neutral type, real
     * function calls on function_call.
     *
     * @param array $aRaw Decoded JSON answer of the Messages API.
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors.
     */
    private function normalizeResponse(array $aRaw): array
    {
        $aOutputs = [];

        foreach ($aRaw['content'] ?? [] as $aBlock) {
            switch ($aBlock['type'] ?? '') {
                case 'text':
                    $aOutputs[] = [
                        'type' => 'text',
                        'text' => $aBlock['text'] ?? '',
                        'annotations' => $this->normalizeCitations($aBlock['citations'] ?? []),
                    ];
                    break;

                case 'server_tool_use':
                    $aOutputs[] = [
                        'type' => ($aBlock['name'] ?? '') === 'web_fetch' ? 'web_fetch' : 'web_search',
                        'status' => 'completed',
                        'call_id' => $aBlock['id'] ?? '',
                        'arguments' => json_encode($aBlock['input'] ?? []),
                    ];
                    break;

                case 'tool_use':
                    if (($aBlock['name'] ?? '') === 'web_search') {
                        $aOutputs[] = [
                            'type' => 'web_search',
                            'status' => 'completed',
                            'call_id' => $aBlock['id'] ?? '',
                            'arguments' => json_encode($aBlock['input'] ?? []),
                        ];
                    } else {
                        $aOutputs[] = [
                            'type' => 'function_call',
                            'name' => $aBlock['name'] ?? '',
                            'call_id' => $aBlock['id'] ?? '',
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
            'model' => $aRaw['model'] ?? '',
            'input_tokens' => $aRaw['usage']['input_tokens'] ?? 0,
            'output_tokens' => $aRaw['usage']['output_tokens'] ?? 0,
            'outputs' => $aOutputs,
            'errors' => [],
        ];
    }

    /**
     * Translates the source citations of a text block into the neutral
     * annotations format.
     *
     * @param array $aCitations Citations from an Anthropic text block.
     * @return array List of annotations with type, url, title and cited_text.
     */
    private function normalizeCitations(array $aCitations): array
    {
        $aResult = [];
        foreach ($aCitations as $aCitation) {
            $aResult[] = [
                'type' => 'url_citation',
                'url' => $aCitation['url'] ?? '',
                'title' => $aCitation['title'] ?? '',
                'cited_text' => $aCitation['cited_text'] ?? '',
            ];
        }
        return $aResult;
    }
}
