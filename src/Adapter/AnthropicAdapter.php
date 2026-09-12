<?php

namespace Conduit\Adapter;

use Conduit\Enum\ToolType;
use Conduit\Exception\UnsupportedCapabilityException;

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
     * Dated `type` tags for the Anthropic-provided server tools
     * (https://platform.claude.com/docs/en/agents-and-tools/tool-use/tool-reference).
     *
     * These versions are capability-keyed, not strict successors: newer dates
     * add optional features (dynamic content filtering, cache-bypass,
     * response-inclusion control) while staying schema-compatible for the
     * fields this adapter sends. We pin the latest of each; older dates
     * (web_search_20250305, web_fetch_20250910, ...) still work at the API if
     * a specific model needs them.
     */
    private const string WEB_SEARCH_TYPE = 'web_search_20260318';
    private const string WEB_FETCH_TYPE  = 'web_fetch_20260318';

    /** Beta flag that unlocks the MCP connector (mcp_servers + mcp_toolset). */
    private const string MCP_BETA = 'mcp-client-2025-11-20';

    /**
     * anthropic-beta flags the current request needs, e.g. MCP_BETA when it
     * carries mcp_servers. Set by chat() from the payload, read by headers(),
     * reset at the start of every chat() call — a beta flag depends on what a
     * request contains, so it lives here and not in the shared request().
     *
     * @var string[]
     */
    private array $aRequestBetas = [];

    /**
     * @param string $sApiKey API key of the Anthropic account.
     */
    public function __construct(private readonly string $sApiKey)
    {
    }

    /**
     * Provider-specific headers for every request. anthropic-beta is added
     * only when the current request collected a flag in $aRequestBetas.
     *
     * @return array Header name => value.
     */
    protected function headers(): array
    {
        $aHeaders = [
            'x-api-key' => $this->sApiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
        ];
        if ($this->aRequestBetas !== []) {
            $aHeaders['anthropic-beta'] = implode(',', $this->aRequestBetas);
        }
        return $aHeaders;
    }

    /**
     * Image generation — not offered by Anthropic.
     *
     * @param array $aPayload Not evaluated.
     * @return array Never returns.
     * @throws UnsupportedCapabilityException Always, because the provider has no image endpoint.
     */
    public function image(array $aPayload): array
    {
        throw new UnsupportedCapabilityException('Image generation is not supported by Anthropic.');
    }

    /**
     * Sends a chat request to the Messages API.
     *
     * @param array $aPayload Neutral payload: model, maxTokens, instruction, context,
     *                        content, tools, jsonSchema, effort, effortSummary.
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors.
     * @throws \Conduit\Exception\TransportException|\Conduit\Exception\ApiException If the HTTP request or the API fails.
     */
    public function chat(array $aPayload): array
    {
        $this->aRequestBetas = [];

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

            // An MCP connection is two body parts: the mcp_toolset entry that
            // buildTools() already put into `tools`, plus these connection
            // details as a top-level sibling. Presence of a server is what
            // gates the beta flag.
            $aMcpServers = $this->buildMcpServers($aPayload['tools']);
            if (!empty($aMcpServers)) {
                $aBody['mcp_servers'] = $aMcpServers;
                $this->aRequestBetas[] = self::MCP_BETA;
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
            // The Anthropic MCP connector runs tool calls without an approval
            // step — an mcpApproval() answer from an OpenAI turn has no place here.
            if (($aMessage['role'] ?? '') === 'mcp_approval_response') {
                continue;
            }
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
     * Translates the neutral tool definitions into the Anthropic format —
     * everything that belongs in the `tools` array of the request body,
     * including the `mcp_toolset` half of an MCP connection. The connection
     * details themselves go into a top-level `mcp_servers` key, built by
     * buildMcpServers(). The built-in tools carry a dated type tag pinned in
     * the WEB_SEARCH_TYPE / WEB_FETCH_TYPE constants. image_generation is
     * skipped silently.
     *
     * @param array $aTools Tool definitions from Tool::webSearch(), ::webFetch(), ::function(), ::mcp().
     * @return array Tools in the Anthropic format, empty when none is supported.
     */
    private function buildTools(array $aTools): array
    {
        $aResult = [];
        foreach ($aTools as $aTool) {
            $sType = $aTool['_type'] ?? '';
            $aBuilt = null;

            switch ($sType) {
                case ToolType::WebSearch->value:
                    $aBuilt = ['type' => self::WEB_SEARCH_TYPE, 'name' => 'web_search'];
                    if (!empty($aTool['max_uses'])) {
                        $aBuilt['max_uses'] = $aTool['max_uses'];
                    }
                    if (!empty($aTool['allowed_domains'])) {
                        $aBuilt['allowed_domains'] = $aTool['allowed_domains'];
                    }
                    if (!empty($aTool['blocked_domains'])) {
                        $aBuilt['blocked_domains'] = $aTool['blocked_domains'];
                    }
                    break;

                case ToolType::WebFetch->value:
                    $aBuilt = ['type' => self::WEB_FETCH_TYPE, 'name' => 'web_fetch'];
                    if (!empty($aTool['max_uses'])) {
                        $aBuilt['max_uses'] = $aTool['max_uses'];
                    }
                    if (!empty($aTool['allowed_domains'])) {
                        $aBuilt['allowed_domains'] = $aTool['allowed_domains'];
                    }
                    if (!empty($aTool['blocked_domains'])) {
                        $aBuilt['blocked_domains'] = $aTool['blocked_domains'];
                    }
                    if (isset($aTool['citations'])) {
                        $aBuilt['citations'] = ['enabled' => (bool)$aTool['citations']];
                    }
                    if (!empty($aTool['max_content_tokens'])) {
                        $aBuilt['max_content_tokens'] = $aTool['max_content_tokens'];
                    }
                    break;

                case ToolType::Function->value:
                    $aBuilt = [
                        'name' => $aTool['name'],
                        'description' => $aTool['description'],
                        'input_schema' => $aTool['parameters'],
                    ];
                    break;

                case ToolType::Mcp->value:
                    // The only entry MCP adds to `tools`: a toolset that points
                    // at the server declared in `mcp_servers` (buildMcpServers()).
                    // Skip it when the server can't be built, so the reference
                    // never dangles. Anthropic's connector has no approval step
                    // and takes no custom headers / description / connector id —
                    // those neutral fields are ignored. allowed_tools becomes an
                    // allowlist: everything off by default, listed tools back on.
                    if (empty($aTool['url'])) {
                        break;
                    }
                    $aBuilt = ['type' => 'mcp_toolset', 'mcp_server_name' => $aTool['name']];
                    if (!empty($aTool['allowed_tools'])) {
                        $aBuilt['default_config'] = ['enabled' => false];
                        foreach ($aTool['allowed_tools'] as $sToolName) {
                            $aBuilt['configs'][$sToolName] = ['enabled' => true];
                        }
                    }
                    break;
            }
            if ($aBuilt !== null) {
                $aResult[] = $aBuilt;
            }
        }
        return $aResult;
    }

    /**
     * Builds the top-level `mcp_servers` array — the connection half of the
     * MCP connector (URL + OAuth token). The `tools` half (one mcp_toolset
     * per server) is built by buildTools(); both skip a server without a URL
     * on the same condition, so a toolset never references a missing server.
     *
     * @param array $aTools All neutral tool definitions of the request.
     * @return array `mcp_servers` entries, empty when no usable MCP tool is present.
     */
    private function buildMcpServers(array $aTools): array
    {
        $aServers = [];
        foreach ($aTools as $aTool) {
            if (($aTool['_type'] ?? '') !== ToolType::Mcp->value || empty($aTool['url'])) {
                continue;
            }
            $aServer = [
                'type' => 'url',
                'url'  => $aTool['url'],
                'name' => $aTool['name'],
            ];
            if (!empty($aTool['authorization_token'])) {
                $aServer['authorization_token'] = $aTool['authorization_token'];
            }
            $aServers[] = $aServer;
        }
        return $aServers;
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

                case 'mcp_tool_use':
                    $aOutputs[] = [
                        'type'         => 'mcp_call',
                        'name'         => $aBlock['name'] ?? '',
                        'server_label' => $aBlock['server_name'] ?? '',
                        'call_id'      => $aBlock['id'] ?? '',
                        'arguments'    => json_encode($aBlock['input'] ?? []),
                    ];
                    break;

                case 'mcp_tool_result':
                    $aOutputs[] = [
                        'type'       => 'mcp_result',
                        'call_id'    => $aBlock['tool_use_id'] ?? '',
                        'mcp_error'  => (bool)($aBlock['is_error'] ?? false),
                        'mcp_output' => $this->flattenMcpResultContent($aBlock['content'] ?? []),
                    ];
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
     * Reduces the content of an mcp_tool_result — a list of text blocks — to a
     * single string. A plain string content (older shape) is returned as is.
     *
     * @param array|string $mContent Content of an mcp_tool_result block.
     * @return string Joined text of the result.
     */
    private function flattenMcpResultContent(array|string $mContent): string
    {
        if (is_string($mContent)) {
            return $mContent;
        }
        $sText = '';
        foreach ($mContent as $aPart) {
            $sText .= $aPart['text'] ?? '';
        }
        return $sText;
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
