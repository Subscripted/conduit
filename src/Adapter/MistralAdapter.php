<?php

namespace Conduit\Adapter;

use Conduit\Enum\StreamEventType;
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
     * Accumulator for streamChat(), reset at the start of every call. The
     * Conversations API sends no "block finished" event (confirmed against
     * the OpenAPI spec, 2026-10-04: only `message.output.delta` /
     * `function.call.delta` exist, no non-delta counterpart) — every output
     * block is therefore assembled here from its deltas and flushed as
     * OutputDone only once `conversation.response.done` arrives.
     *
     * @var array<int, array{kind: string, text: string, name?: string, call_id?: string}>
     */
    private array $aStreamOutputs = [];

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
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors, warnings.
     * @throws TransportException|ApiException If the HTTP request or the API fails.
     */
    public function chat(array $aPayload): array
    {
        $this->resetWarnings();

        $aBody = $this->buildChatBody($aPayload);

        $aRaw = $this->request(self::BASE_URL . '/conversations', $aBody);
        return $this->normalizeResponse($aRaw, $aPayload['model'], !empty($aPayload['jsonSchema']));
    }

    /**
     * Sends a chat request to the Conversations API and streams the answer.
     *
     * Event names confirmed against Mistral's public OpenAPI spec
     * (docs.mistral.ai/openapi.yaml, `ConversationEvents` schema, 2026-10-04).
     * Unlike Anthropic/OpenAI, the Conversations API sends no "block
     * finished" event — see normalizeStreamEvent() for how blocks are
     * assembled from deltas instead.
     *
     * @param array $aPayload Neutral payload, same shape as chat().
     * @return \Generator<array> Normalized stream events.
     * @throws TransportException|ApiException If the HTTP request or the API fails.
     */
    public function streamChat(array $aPayload): \Generator
    {
        $this->resetWarnings();
        $this->aStreamOutputs = [];

        $aBody          = $this->buildChatBody($aPayload);
        $bJsonRequested = !empty($aPayload['jsonSchema']);

        foreach ($this->requestStream(self::BASE_URL . '/conversations', $aBody) as $aEvent) {
            yield from $this->normalizeStreamEvent($aEvent, $aPayload['model'], $bJsonRequested);
        }
    }

    /**
     * Builds the Conversations API request body shared by chat() and streamChat().
     *
     * @param array $aPayload Neutral payload: model, maxTokens, instruction, context,
     *                        content, user, tools, effort, jsonSchema.
     * @return array Request body for POST /conversations.
     */
    private function buildChatBody(array $aPayload): array
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

        return $aBody;
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
                : $this->transformContent(array_is_list($mContent) ? $mContent : [$mContent]),
        ];
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
     * recorded via warn() and skipped rather than mistranslated.
     *
     * @param array $aTools Tool definitions from Tool::function() and others.
     * @return array Tools in the Mistral format, empty when none is supported.
     */
    private function buildTools(array $aTools): array
    {
        $aResult = [];
        foreach ($aTools as $aTool) {
            $sType = $aTool['_type'] ?? '';
            if ($sType !== ToolType::Function->value) {
                $this->warn("Tool type '{$sType}' is not supported by the Mistral adapter yet and was skipped.");
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
     * @param array  $aRaw           Decoded JSON answer of the Conversations API.
     * @param string $sModel         Model id that was sent in the request.
     * @param bool   $bJsonRequested Whether the request carried a jsonSchema — a text chunk is
     *                               then the structured answer and normalizes to type 'json'.
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors, warnings.
     */
    private function normalizeResponse(array $aRaw, string $sModel, bool $bJsonRequested = false): array
    {
        $aOutputs = [];

        foreach ($aRaw['outputs'] ?? [] as $aEntry) {
            foreach ($this->mapEntryToBlocks($aEntry, $bJsonRequested) as $aBlock) {
                $aOutputs[] = $aBlock;
            }
        }

        return [
            'model'         => $sModel,
            'input_tokens'  => $aRaw['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $aRaw['usage']['completion_tokens'] ?? 0,
            'outputs'       => $aOutputs,
            'errors'        => [],
            'warnings'      => $this->getWarnings(),
        ];
    }

    /**
     * Translates one Conversations API output entry into zero or more neutral
     * output blocks. Used by normalizeResponse() for the buffered response;
     * streamChat() assembles its blocks differently (flushStreamOutputs()) —
     * the Conversations API's streaming entries have no non-delta
     * counterpart to run through this method. Entry types this adapter never
     * causes the API to return (tool.execution, agent.handoff) are left
     * unhandled.
     *
     * @param array $aEntry         One entry of the Conversations API `outputs` array.
     * @param bool  $bJsonRequested Whether the request carried a jsonSchema — a text chunk is
     *                              then the structured answer and normalizes to type 'json'.
     * @return array Zero or more normalized output blocks.
     */
    private function mapEntryToBlocks(array $aEntry, bool $bJsonRequested): array
    {
        $aBlocks = [];

        switch ($aEntry['type'] ?? '') {
            case 'message.output':
                $mContent = $aEntry['content'] ?? '';
                if (is_string($mContent)) {
                    if ($mContent !== '') {
                        $aBlocks[] = ['type' => $bJsonRequested ? 'json' : 'text', 'text' => $mContent];
                    }
                } else {
                    foreach ($mContent as $aChunk) {
                        $aBlocks[] = $this->normalizeMessageChunk($aChunk, $bJsonRequested);
                    }
                }
                break;

            case 'function.call':
                $mArguments = $aEntry['arguments'] ?? '{}';
                $aBlocks[] = [
                    'type'      => 'function_call',
                    'name'      => $aEntry['name'] ?? '',
                    'call_id'   => $aEntry['tool_call_id'] ?? '',
                    'arguments' => is_array($mArguments) ? json_encode($mArguments) : (string) $mArguments,
                ];
                break;
        }

        return $aBlocks;
    }

    // ── Stream normalizer ────────────────────────────────────────────────

    /**
     * Translates one decoded SSE event of the Conversations API stream into
     * zero or more neutral stream events. Confirmed against the
     * `ConversationEvents` schema in Mistral's OpenAPI spec (2026-10-04):
     * the SSE `type` values are `conversation.response.started/.done/.error`,
     * `message.output.delta`, `function.call.delta`, plus `tool.execution.*`
     * and `agent.handoff.*` for built-in connectors / agents this adapter
     * never triggers (only `Tool::function()` is translated, see
     * buildTools()).
     *
     * There is no "block finished" event — `message.output.delta` and
     * `function.call.delta` are the only content-bearing types, each keyed by
     * `output_index`. Every block is therefore accumulated in
     * $aStreamOutputs and only turned into an OutputDone once
     * `conversation.response.done` arrives (flushStreamOutputs()).
     *
     * @param array  $aData          Decoded JSON of one SSE event ('data:' line(s)).
     * @param string $sModel         Model id that was sent in the request (the Conversations
     *                               API carries no model field of its own, see normalizeResponse()).
     * @param bool   $bJsonRequested Whether the request carried a jsonSchema.
     * @return \Generator<array> Zero or more normalized stream events.
     */
    private function normalizeStreamEvent(array $aData, string $sModel, bool $bJsonRequested): \Generator
    {
        switch ($aData['type'] ?? '') {
            // Lifecycle marker, carries no content of its own.
            case 'conversation.response.started':
                break;

            case 'message.output.delta':
                $iIndex    = $aData['output_index'] ?? 0;
                $aFragment = $this->extractContentFragment($aData['content'] ?? '');
                if ($aFragment === null) {
                    break; // a chunk type with no textual equivalent (image/file/tool_reference)
                }
                [$sKind, $sText] = $aFragment;
                $this->aStreamOutputs[$iIndex] ??= ['kind' => $sKind, 'text' => ''];
                $this->aStreamOutputs[$iIndex]['text'] .= $sText;
                yield [
                    'type'  => $sKind === 'thinking' ? StreamEventType::ThinkingDelta->value : StreamEventType::TextDelta->value,
                    'index' => $iIndex,
                    'delta' => $sText,
                ];
                break;

            case 'function.call.delta':
                $iIndex = $aData['output_index'] ?? 0;
                $this->aStreamOutputs[$iIndex] ??= ['kind' => 'function_call', 'text' => '', 'name' => '', 'call_id' => ''];
                if (!empty($aData['name'])) {
                    $this->aStreamOutputs[$iIndex]['name'] = $aData['name'];
                }
                if (!empty($aData['tool_call_id'])) {
                    $this->aStreamOutputs[$iIndex]['call_id'] = $aData['tool_call_id'];
                }
                $sFragment = (string) ($aData['arguments'] ?? '');
                $this->aStreamOutputs[$iIndex]['text'] .= $sFragment;
                yield ['type' => StreamEventType::FunctionCallArgumentsDelta->value, 'index' => $iIndex, 'delta' => $sFragment];
                break;

            case 'conversation.response.done':
                foreach ($this->flushStreamOutputs($bJsonRequested) as $iIndex => $aBlock) {
                    yield ['type' => StreamEventType::OutputDone->value, 'index' => $iIndex, 'output' => $aBlock];
                }
                yield [
                    'type' => StreamEventType::ResponseDone->value,
                    'model' => $sModel,
                    'input_tokens' => $aData['usage']['prompt_tokens'] ?? 0,
                    'output_tokens' => $aData['usage']['completion_tokens'] ?? 0,
                    'warnings' => $this->getWarnings(),
                ];
                break;

            case 'conversation.response.error':
                yield [
                    'type' => StreamEventType::Error->value,
                    'error' => ApiException::fromResponse(
                        (int) ($aData['code'] ?? 0),
                        (string) ($aData['message'] ?? 'Unknown streaming error'),
                        ['error' => ['message' => $aData['message'] ?? '']],
                    ),
                ];
                break;

            // Built-in connectors (web_search, code_interpreter, image
            // generation via agents) and agent handoffs — this adapter never
            // builds a request that triggers these, so they're intentionally
            // not mapped rather than guessed at.
            case 'tool.execution.started':
            case 'tool.execution.delta':
            case 'tool.execution.done':
            case 'agent.handoff.started':
            case 'agent.handoff.done':
                break;

            default:
                $this->warn("Unhandled Mistral stream event type '" . ($aData['type'] ?? '') . "' was skipped. Keys present: " . implode(', ', array_keys($aData)));
                break;
        }
    }

    /**
     * Turns every output block accumulated in $aStreamOutputs into a
     * normalized output block, keyed by output_index, and clears the
     * accumulator. Called once per stream, when conversation.response.done
     * arrives — see normalizeStreamEvent().
     *
     * @param bool $bJsonRequested Whether the request carried a jsonSchema — a text block is
     *                             then the structured answer and normalizes to type 'json'.
     * @return array<int, array> Normalized output blocks, keyed by output_index.
     */
    private function flushStreamOutputs(bool $bJsonRequested): array
    {
        $aResult = [];
        foreach ($this->aStreamOutputs as $iIndex => $aOutput) {
            $aResult[$iIndex] = match ($aOutput['kind']) {
                'text' => ['type' => $bJsonRequested ? 'json' : 'text', 'text' => $aOutput['text']],
                'thinking' => ['type' => 'thinking', 'thinking' => $aOutput['text']],
                'function_call' => [
                    'type' => 'function_call',
                    'name' => $aOutput['name'] ?? '',
                    'call_id' => $aOutput['call_id'] ?? '',
                    'arguments' => $aOutput['text'],
                ],
                default => ['type' => 'text', 'text' => $aOutput['text']],
            };
        }
        $this->aStreamOutputs = [];
        return $aResult;
    }

    /**
     * Pulls the readable fragment out of one message.output.delta's
     * `content` field — a plain string, or a single content chunk object
     * (`{"type": "text", "text": "..."}` etc., per OutputContentChunks in
     * the OpenAPI spec — a single chunk, not an array of chunks, unlike the
     * non-streaming message.output's content). Only text and thinking chunks
     * have a textual equivalent; image/file/tool_reference chunks don't
     * stream as text and are skipped.
     *
     * @param string|array $mContent The delta event's `content` field.
     * @return array{0: string, 1: string}|null [kind, text] ('text' or
     *                                           'thinking'), or null when the
     *                                           chunk carries no text.
     */
    private function extractContentFragment(string|array $mContent): ?array
    {
        if (is_string($mContent)) {
            return ['text', $mContent];
        }

        switch ($mContent['type'] ?? '') {
            case 'text':
                return ['text', $mContent['text'] ?? ''];

            case 'thinking':
                $sThinking = '';
                foreach ($mContent['thinking'] ?? [] as $aPart) {
                    if (($aPart['type'] ?? '') === 'text') {
                        $sThinking .= $aPart['text'] ?? '';
                    }
                }
                return ['thinking', $sThinking];

            default:
                return null;
        }
    }

    /**
     * Translates a single content chunk of a message.output entry into the
     * neutral format. A thinking chunk holds its own list of text
     * sub-chunks, which are joined into one string. Unknown chunk types are
     * treated as text so the content is not lost.
     *
     * @param array $aChunk         Chunk from the content array of a message.output entry.
     * @param bool  $bJsonRequested Whether the request carried a jsonSchema — a text chunk is
     *                              then the structured answer and normalizes to type 'json'.
     * @return array Normalized output block.
     */
    private function normalizeMessageChunk(array $aChunk, bool $bJsonRequested = false): array
    {
        switch ($aChunk['type'] ?? '') {
            case 'text':
                return ['type' => $bJsonRequested ? 'json' : 'text', 'text' => $aChunk['text'] ?? ''];
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
