<?php

namespace Conduit\Adapter;

use Conduit\Enum\ToolType;
use Conduit\Exception\ApiException;
use Conduit\Exception\TransportException;
use Conduit\Exception\UnsupportedCapabilityException;

/**
 * Adapter for the Mistral Conversations API.
 *
 * https://docs.mistral.ai/api/endpoint/conversations
 *
 * Translates the provider-neutral payload into the body of the Conversations
 * endpoint and the response back into the normalized array that ChatResponse
 * is built from. The rest of the application therefore only knows the
 * neutral format and no Mistral specifics.
 *
 * Chosen over the plain Chat Completions API (/v1/chat/completions) because
 * Mistral's own docs state its built-in tools (web search, code interpreter,
 * image generation, ...) and the richer function-calling entry types only
 * work on Conversations/Agents, not on Chat Completions.
 *
 * The Conversations API is stateful server-side (a conversation_id can be
 * reused to append further turns), but this adapter uses it the same way
 * OpenAIAdapter uses the Responses API: statelessly, POSTing the full
 * history as `inputs` on every call rather than tracking a conversation_id
 * between requests — the caller already resends the full Context every turn.
 *
 * Mistral has no image generation endpoint comparable to OpenAI's — image
 * generation there is a tool inside the Conversations API producing a
 * tool-file output, not a standalone endpoint — so image() always throws.
 * Only Tool::function() is translated into a Conversations tool: the other
 * neutral tool types (web search, MCP, ...) don't map onto Mistral's
 * built-in connectors (`web_search`, `code_interpreter`, `connector_id`, ...)
 * and are silently skipped rather than guessed at.
 */
class MistralAdapter extends AbstractLLMAdapter
{
    private const string BASE_URL = 'https://api.mistral.ai/v1';

    /**
     * @param string $sApiKey API key of the Mistral account.
     */
    public function __construct(private readonly string $sApiKey) {}

    /**
     * Provider-specific headers for every request.
     *
     * @return array Header name => value.
     */
    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->sApiKey];
    }

    /**
     * Image generation — not offered as a plain endpoint by Mistral.
     *
     * @param array $aPayload Not evaluated.
     * @return array Never returns.
     * @throws UnsupportedCapabilityException Always, because the provider has no image endpoint.
     */
    public function image(array $aPayload): array
    {
        throw new UnsupportedCapabilityException('Image generation is not supported by Mistral.');
    }

    /**
     * Sends a chat request to the Conversations API.
     *
     * @param array $aPayload Neutral payload: model, maxTokens, instruction, context,
     *                        content, user, tools, effort, jsonSchema.
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors.
     * @throws TransportException|ApiException If the HTTP request or the API fails.
     */
    public function chat(array $aPayload): array
    {
        $aBody = [
            'model'  => $aPayload['model'],
            'stream' => false,
            'inputs' => $this->buildInputs($aPayload),
        ];

        if (!empty($aPayload['instruction'])) {
            $aBody['instructions'] = $aPayload['instruction'];
        }
        if (!empty($aPayload['tools'])) {
            $aBuiltTools = $this->buildTools($aPayload['tools']);
            if (!empty($aBuiltTools)) {
                $aBody['tools'] = $aBuiltTools;
            }
        }

        // max_tokens, reasoning_effort and response_format live inside
        // completion_args here, unlike the top-level fields of Chat Completions.
        $aCompletionArgs = [];
        if (!empty($aPayload['maxTokens'])) {
            $aCompletionArgs['max_tokens'] = $aPayload['maxTokens'];
        }
        // 'low' is Chat::effort()'s default — sending it unconditionally like
        // the other adapters do would mean every Mistral call carries a
        // reasoning_effort value, and non-reasoning models (e.g. the classic
        // open-mistral-* line) reject anything outside {'none', 'high'} with
        // a 422. Only forward it once the caller explicitly asked for more.
        if (!empty($aPayload['effort']) && $aPayload['effort'] !== 'low') {
            $aCompletionArgs['reasoning_effort'] = $aPayload['effort'];
        }
        if (!empty($aPayload['jsonSchema'])) {
            $aCompletionArgs['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name'   => 'response',
                    'schema' => $aPayload['jsonSchema'],
                    'strict' => true,
                ],
            ];
        }
        if (!empty($aCompletionArgs)) {
            $aBody['completion_args'] = $aCompletionArgs;
        }

        $aRaw = $this->request(self::BASE_URL . '/conversations', $aBody);
        return $this->normalizeResponse($aRaw, $aPayload['model']);
    }

    // ── Input builder ─────────────────────────────────────────────────────

    /**
     * Builds the `inputs` list from the conversation history plus the
     * current user input. The system instruction is not part of this list —
     * it goes into the top-level `instructions` field instead.
     *
     * @param array $aPayload Neutral payload with context, content and user.
     * @return array List of entries in the Mistral Conversations format.
     */
    private function buildInputs(array $aPayload): array
    {
        $aInputs = [];

        foreach ($aPayload['context'] ?? [] as $aMessage) {
            // Mistral's Conversations API has no MCP approval concept — an
            // mcpApproval() answer from an OpenAI turn has no place here.
            if (($aMessage['role'] ?? '') === 'mcp_approval_response') {
                continue;
            }
            $aInputs[] = $this->transformContextMessage($aMessage);
        }

        $aInputs[] = [
            'type'    => 'message.input',
            'role'    => $aPayload['user'] ?? 'user',
            'content' => $this->transformContent($aPayload['content'] ?? []),
        ];

        return $aInputs;
    }

    /**
     * Translates a message from the conversation history into a Mistral
     * input entry. Tool-call results become a function.result entry rather
     * than a message.input.
     *
     * @param array $aMessage Message with role, content and, for tool results, tool_call_id.
     * @return array Entry in the Mistral Conversations format.
     */
    private function transformContextMessage(array $aMessage): array
    {
        if ($aMessage['role'] === 'tool_result') {
            $mContent = $aMessage['content'];
            return [
                'type'         => 'function.result',
                'tool_call_id' => $aMessage['tool_call_id'],
                'result'       => is_array($mContent) ? json_encode($mContent) : (string) $mContent,
            ];
        }

        $mContent = $aMessage['content'];
        return [
            'type'    => 'message.input',
            'role'    => $aMessage['role'],
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
     * Translates the neutral content blocks into Mistral chunks. Files are
     * called 'document_url' there; images and files come as a plain URL or a
     * data: URI depending on their origin. Unknown types are passed through
     * unchanged.
     *
     * @param array $aContent Blocks from Content::text(), ::image() and ::file().
     * @return array Chunks in the Mistral format.
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
                    $sUrl      = $aBlock['url'] ?? ('data:' . ($aBlock['media_type'] ?? 'image/jpeg') . ';base64,' . $aBlock['data']);
                    $aResult[] = ['type' => 'image_url', 'image_url' => $sUrl];
                    break;
                case 'file':
                    $sUrl  = $aBlock['url'] ?? ('data:' . ($aBlock['media_type'] ?? 'application/pdf') . ';base64,' . $aBlock['data']);
                    $aFile = ['type' => 'document_url', 'document_url' => $sUrl];
                    if (isset($aBlock['filename'])) {
                        $aFile['document_name'] = $aBlock['filename'];
                    }
                    $aResult[] = $aFile;
                    break;
                default:
                    $aResult[] = $aBlock;
            }
        }
        return $aResult;
    }

    // ── Tool builder ──────────────────────────────────────────────────────

    /**
     * Translates the neutral tool definitions into the Mistral format. Only
     * Tool::function() is supported — the other neutral tool types describe
     * shapes (a URL-addressed MCP server, OpenAI-style web search params)
     * that don't correspond to Mistral's built-in connectors, so they are
     * silently skipped rather than mistranslated.
     *
     * @param array $aTools Tool definitions from Tool::function() and others.
     * @return array Tools in the Mistral format, empty when none is supported.
     */
    private function buildTools(array $aTools): array
    {
        $aResult = [];
        foreach ($aTools as $aTool) {
            if (($aTool['_type'] ?? '') !== ToolType::Function->value) {
                continue;
            }
            $aResult[] = [
                'type'     => 'function',
                'function' => [
                    'name'        => $aTool['name'],
                    'description' => $aTool['description'],
                    'parameters'  => $aTool['parameters'],
                ],
            ];
        }
        return $aResult;
    }

    // ── Response normalizer ───────────────────────────────────────────────

    /**
     * Translates the raw Conversations API answer into the neutral format
     * for ChatResponse. Entry types this adapter never causes the API to
     * return (tool.execution, agent.handoff — both require built-in
     * connectors or an agent this adapter never sends) are left unhandled.
     *
     * Unlike Chat Completions, the Conversations response carries no `model`
     * field of its own — the model id from the request is passed back in
     * instead so ChatResponse::getModel() isn't left empty.
     *
     * @param array  $aRaw    Decoded JSON answer of the Conversations API.
     * @param string $sModel  Model id that was sent in the request.
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors.
     */
    private function normalizeResponse(array $aRaw, string $sModel): array
    {
        $aOutputs = [];

        foreach ($aRaw['outputs'] ?? [] as $aEntry) {
            switch ($aEntry['type'] ?? '') {
                case 'message.output':
                    $mContent = $aEntry['content'] ?? '';
                    if (is_string($mContent)) {
                        if ($mContent !== '') {
                            $aOutputs[] = ['type' => 'text', 'text' => $mContent];
                        }
                    } else {
                        foreach ($mContent as $aChunk) {
                            $aOutputs[] = $this->normalizeMessageChunk($aChunk);
                        }
                    }
                    break;

                case 'function.call':
                    $mArguments = $aEntry['arguments'] ?? '{}';
                    $aOutputs[] = [
                        'type'      => 'function_call',
                        'name'      => $aEntry['name'] ?? '',
                        'call_id'   => $aEntry['tool_call_id'] ?? '',
                        'arguments' => is_array($mArguments) ? json_encode($mArguments) : (string) $mArguments,
                    ];
                    break;
            }
        }

        return [
            'model'         => $sModel,
            'input_tokens'  => $aRaw['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $aRaw['usage']['completion_tokens'] ?? 0,
            'outputs'       => $aOutputs,
            'errors'        => [],
        ];
    }

    /**
     * Translates a single content chunk of a message.output entry into the
     * neutral format. A thinking chunk holds its own list of text
     * sub-chunks, which are joined into one string. Unknown chunk types are
     * treated as text so the content is not lost.
     *
     * @param array $aChunk Chunk from the content array of a message.output entry.
     * @return array Normalized output block.
     */
    private function normalizeMessageChunk(array $aChunk): array
    {
        switch ($aChunk['type'] ?? '') {
            case 'text':
                return ['type' => 'text', 'text' => $aChunk['text'] ?? ''];
            case 'thinking':
                $sThinking = '';
                foreach ($aChunk['thinking'] ?? [] as $aPart) {
                    $sThinking .= $aPart['text'] ?? '';
                }
                return ['type' => 'thinking', 'thinking' => $sThinking];
            default:
                return ['type' => 'text', 'text' => $aChunk['text'] ?? ''];
        }
    }
}
