<?php

namespace Conduit\Adapter;

use Conduit\Enum\ToolType;

/**
 * Adapter for the OpenAI Responses API and the Images API.
 *
 * https://platform.openai.com/docs/api-reference/responses
 * https://platform.openai.com/docs/api-reference/images
 *
 * Translates the provider-neutral payload into the body of the OpenAI
 * endpoints and the response back into the normalized array that
 * ChatResponse / ImageResponse are built from. The rest of the application
 * therefore only knows the neutral format and no OpenAI specifics.
 */
class OpenAIAdapter extends AbstractLLMAdapter
{
    private const string BASE_URL = 'https://api.openai.com/v1';

    /**
     * @param string $sApiKey API key of the OpenAI account.
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
     * Sends a chat request to the Responses API.
     *
     * @param array $aPayload Neutral payload: model, maxTokens, instruction, context,
     *                        content, user, tools, effort, effortSummary.
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors.
     * @throws \Conduit\Exception\TransportException|\Conduit\Exception\ApiException If the HTTP request or the API fails.
     */
    public function chat(array $aPayload): array
    {
        $aBody = [
            'model' => $aPayload['model'],
            'input' => $this->buildInput($aPayload),
        ];

        if (!empty($aPayload['instruction'])) {
            $aBody['instructions'] = $aPayload['instruction'];
        }
        if (!empty($aPayload['maxTokens'])) {
            $aBody['max_output_tokens'] = $aPayload['maxTokens'];
        }
        if (!empty($aPayload['effort'])) {
            $aBody['reasoning'] = ['effort' => $aPayload['effort']];
            if (!empty($aPayload['effortSummary'])) {
                $aBody['reasoning']['summary'] = 'auto';
            }
        }
        if (!empty($aPayload['tools'])) {
            $aBuiltTools = $this->buildTools($aPayload['tools']);
            if (!empty($aBuiltTools)) {
                $aBody['tools'] = $aBuiltTools;
            }
        }

        $aRaw = $this->request(self::BASE_URL . '/responses', $aBody);
        return $this->normalizeResponse($aRaw);
    }

    /**
     * Creates or edits an image via the Images API. Uses /images/edits
     * because the endpoint also works without input images and thus covers
     * both cases. For pure text-to-image requests, switch to /images/generations.
     *
     * @param array $aPayload Neutral payload: model, prompt, images, size, quality, outputFormat.
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors.
     * @throws \Conduit\Exception\TransportException|\Conduit\Exception\ApiException If the HTTP request or the API fails.
     */
    public function image(array $aPayload): array
    {
        $aBody = [
            'model'  => $aPayload['model'],
            'prompt' => $aPayload['prompt'],
        ];

        foreach ($aPayload['images'] ?? [] as $sUrl) {
            $aBody['images'][] = ['image_url' => $sUrl];
        }

        if (!empty($aPayload['size']))         $aBody['size']          = $aPayload['size'];
        if (!empty($aPayload['quality']))      $aBody['quality']       = $aPayload['quality'];
        if (!empty($aPayload['outputFormat'])) $aBody['output_format'] = $aPayload['outputFormat'];

        $aRaw = $this->request(self::BASE_URL . '/images/edits', $aBody);
        return $this->normalizeImageResponse($aRaw);
    }

    // ── Input builder ─────────────────────────────────────────────────────

    /**
     * Builds the input list from the conversation history plus the current
     * user input.
     *
     * @param array $aPayload Neutral payload with context, content and user.
     * @return array List of messages with role and content.
     */
    private function buildInput(array $aPayload): array
    {
        $aMessages = [];

        foreach ($aPayload['context'] ?? [] as $aMessage) {
            $aMessages[] = $this->transformContextMessage($aMessage);
        }

        $sRole = $aPayload['user'] ?? 'user';

        $aMessages[] = [
            'role'    => $sRole,
            'content' => $this->transformContent($aPayload['content'] ?? [], $sRole),
        ];

        return $aMessages;
    }

    /**
     * Translates a message from the conversation history into the OpenAI
     * format. Tool-call results are not a message with a role there but a
     * separate item of type function_call_output.
     *
     * @param array $aMessage Message with role, content and, for tool results, tool_call_id.
     * @return array Item in the OpenAI format.
     */
    private function transformContextMessage(array $aMessage): array
    {
        if ($aMessage['role'] === 'tool_result') {
            $mContent = $aMessage['content'];
            return [
                'type'    => 'function_call_output',
                'call_id' => $aMessage['tool_call_id'],
                'output'  => is_array($mContent) ? json_encode($mContent) : (string) $mContent,
            ];
        }

        if ($aMessage['role'] === 'mcp_approval_response') {
            return [
                'type'                => 'mcp_approval_response',
                'approval_request_id' => $aMessage['approval_request_id'],
                'approve'             => (bool) $aMessage['approve'],
            ];
        }

        $mContent = $aMessage['content'];
        return [
            'role'    => $aMessage['role'],
            'content' => is_string($mContent)
                ? $mContent
                : $this->transformContent($this->wrapIfSingleBlock($mContent), $aMessage['role']),
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
     * Translates the neutral content blocks into OpenAI blocks.
     *
     * The text type depends on the role: what came from the model must be
     * sent back as output_text, everything else as input_text. Base64 images
     * and files go as a data: URI, unknown types are passed through unchanged.
     *
     * @param array  $aContent Blocks from Content::text(), ::image() and ::file().
     * @param string $sRole    Role of the message, drives the text type (default 'user').
     * @return array Blocks in the OpenAI format.
     */
    private function transformContent(array $aContent, string $sRole = 'user'): array
    {
        $sTextType = $sRole === 'assistant' ? 'output_text' : 'input_text';
        $aResult   = [];
        foreach ($aContent as $aBlock) {
            switch ($aBlock['type'] ?? '') {
                case 'text':
                    $aResult[] = ['type' => $sTextType, 'text' => $aBlock['text']];
                    break;
                case 'image':
                    if (isset($aBlock['url'])) {
                        $aResult[] = ['type' => 'input_image', 'image_url' => $aBlock['url']];
                    } else {
                        $sDataUri  = 'data:' . ($aBlock['media_type'] ?? 'image/jpeg') . ';base64,' . $aBlock['data'];
                        $aResult[] = ['type' => 'input_image', 'image_url' => $sDataUri];
                    }
                    break;
                case 'file':
                    $aFile = ['type' => 'input_file'];
                    if (isset($aBlock['url'])) {
                        $aFile['file_url'] = $aBlock['url'];
                    } else {
                        $aFile['file_data'] = 'data:' . ($aBlock['media_type'] ?? 'application/octet-stream') . ';base64,' . $aBlock['data'];
                    }
                    if (isset($aBlock['filename'])) {
                        $aFile['filename'] = $aBlock['filename'];
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
     * Translates the neutral tool definitions into the OpenAI format.
     * Optional values are only set when filled so the API uses its own
     * defaults. Unknown tool types are silently skipped.
     *
     * @param array $aTools Tool definitions from Tool::webSearch(), ::function(),
     *                      ::imageGeneration() and ::mcp().
     * @return array Tools in the OpenAI format, empty when none is supported.
     */
    private function buildTools(array $aTools): array
    {
        $aResult = [];
        foreach ($aTools as $aTool) {
            $sType  = $aTool['_type'] ?? '';
            $aBuilt = null;

            switch ($sType) {
                case ToolType::WebSearch->value:
                    $aBuilt = ['type' => 'web_search', 'search_context_size' => $aTool['context_size'] ?? 'medium'];
                    if (!empty($aTool['user_location']))   $aBuilt['user_location'] = $aTool['user_location'];
                    $aFilters = [];
                    if (!empty($aTool['allowed_domains'])) $aFilters['allowed_domains'] = $aTool['allowed_domains'];
                    if (!empty($aTool['blocked_domains'])) $aFilters['blocked_domains'] = $aTool['blocked_domains'];
                    if (!empty($aFilters))                 $aBuilt['filters'] = $aFilters;
                    break;

                case ToolType::Function->value:
                    $aBuilt = [
                        'type'     => 'function',
                        'function' => [
                            'name'        => $aTool['name'],
                            'description' => $aTool['description'],
                            'parameters'  => $aTool['parameters'],
                        ],
                    ];
                    break;

                case ToolType::ImageGeneration->value:
                    $aBuilt = ['type' => 'image_generation'];
                    if (!empty($aTool['model']))              $aBuilt['model']              = $aTool['model'];
                    if (!empty($aTool['size']))               $aBuilt['size']               = $aTool['size'];
                    if (!empty($aTool['quality']))            $aBuilt['quality']            = $aTool['quality'];
                    if (!empty($aTool['background']))         $aBuilt['background']         = $aTool['background'];
                    if (!empty($aTool['moderation']))         $aBuilt['moderation']         = $aTool['moderation'];
                    if (!empty($aTool['input_fidelity']))     $aBuilt['input_fidelity']     = $aTool['input_fidelity'];
                    if (!empty($aTool['output_format']))      $aBuilt['output_format']      = $aTool['output_format'];
                    if (isset($aTool['output_compression']))  $aBuilt['output_compression'] = $aTool['output_compression'];
                    if (isset($aTool['partial_images']))      $aBuilt['partial_images']     = $aTool['partial_images'];
                    break;

                case ToolType::Mcp->value:
                    $aBuilt = [
                        'type'             => 'mcp',
                        'server_label'     => $aTool['name'],
                        'require_approval' => $aTool['require_approval'] ?? 'always',
                    ];
                    // server_url and connector_id are mutually exclusive — a
                    // built-in connector carries no URL.
                    if (!empty($aTool['connector_id'])) {
                        $aBuilt['connector_id'] = $aTool['connector_id'];
                    } elseif (!empty($aTool['url'])) {
                        $aBuilt['server_url'] = $aTool['url'];
                    }
                    if (!empty($aTool['description']))         $aBuilt['server_description'] = $aTool['description'];
                    if (!empty($aTool['authorization_token'])) $aBuilt['authorization']     = $aTool['authorization_token'];
                    if (!empty($aTool['headers']))             $aBuilt['headers']           = $aTool['headers'];
                    if (!empty($aTool['allowed_tools']))       $aBuilt['allowed_tools']     = $aTool['allowed_tools'];
                    break;
            }

            if ($aBuilt !== null) {
                $aResult[] = $aBuilt;
            }
        }
        return $aResult;
    }

    // ── Response normalizer ───────────────────────────────────────────────

    /**
     * Translates the raw Responses API answer into the neutral format for
     * ChatResponse. A message item can hold several blocks and is unfolded.
     * A reasoning item without a summary yields no text and is skipped so no
     * empty thinking outputs appear.
     *
     * @param array $aRaw Decoded JSON answer of the Responses API.
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors.
     */
    private function normalizeResponse(array $aRaw): array
    {
        $aOutputs = [];

        foreach ($aRaw['output'] ?? [] as $aItem) {
            switch ($aItem['type'] ?? '') {
                case 'message':
                    foreach ($aItem['content'] ?? [] as $aBlock) {
                        $aOutputs[] = $this->normalizeMessageBlock($aBlock);
                    }
                    break;

                case 'function_call':
                    $aOutputs[] = [
                        'type'      => 'function_call',
                        'name'      => $aItem['name'] ?? '',
                        'call_id'   => $aItem['call_id'] ?? $aItem['id'] ?? '',
                        'arguments' => $aItem['arguments'] ?? '{}',
                    ];
                    break;

                case 'web_search_call':
                    $aOutputs[] = [
                        'type'   => 'web_search',
                        'status' => $aItem['status'] ?? 'completed',
                    ];
                    break;

                case 'mcp_call':
                    $aOutputs[] = [
                        'type'         => 'mcp_call',
                        'name'         => $aItem['name'] ?? '',
                        'server_label' => $aItem['server_label'] ?? '',
                        'call_id'      => $aItem['id'] ?? '',
                        'arguments'    => $aItem['arguments'] ?? '{}',
                    ];
                    // OpenAI bundles the result into the same item; split it
                    // off into its own block so it reads the same as Anthropic.
                    if (isset($aItem['output']) || !empty($aItem['error'])) {
                        $aOutputs[] = [
                            'type'       => 'mcp_result',
                            'call_id'    => $aItem['id'] ?? '',
                            'mcp_error'  => !empty($aItem['error']),
                            'mcp_output' => $aItem['error'] ?? (string) ($aItem['output'] ?? ''),
                        ];
                    }
                    break;

                case 'mcp_list_tools':
                    $aOutputs[] = [
                        'type'         => 'mcp_list_tools',
                        'server_label' => $aItem['server_label'] ?? '',
                        'mcp_tools'    => array_map(
                            static fn (array $aTool): string => $aTool['name'] ?? '',
                            $aItem['tools'] ?? [],
                        ),
                    ];
                    break;

                case 'mcp_approval_request':
                    $aOutputs[] = [
                        'type'         => 'mcp_approval_request',
                        'name'         => $aItem['name'] ?? '',
                        'server_label' => $aItem['server_label'] ?? '',
                        'call_id'      => $aItem['id'] ?? '',
                        'arguments'    => $aItem['arguments'] ?? '{}',
                    ];
                    break;

                case 'image_generation_call':
                    $sResult    = $aItem['result'] ?? '';
                    $aOutputs[] = $this->normalizeImageResult($sResult, $aItem['status'] ?? 'completed');
                    break;

                case 'reasoning':
                    $sThinking = '';
                    foreach ($aItem['summary'] ?? [] as $aSummary) {
                        $sThinking .= $aSummary['text'] ?? '';
                    }
                    if ($sThinking !== '') {
                        $aOutputs[] = ['type' => 'thinking', 'thinking' => $sThinking];
                    }
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

    /**
     * Translates a single block of a message into the neutral format.
     * Unknown block types are treated as text so the content is not lost.
     *
     * @param array $aBlock Block from the content array of a message.
     * @return array Normalized output block.
     */
    private function normalizeMessageBlock(array $aBlock): array
    {
        switch ($aBlock['type'] ?? '') {
            case 'output_text':
                return [
                    'type'        => 'text',
                    'text'        => $aBlock['text'] ?? '',
                    'annotations' => $this->normalizeAnnotations($aBlock['annotations'] ?? []),
                ];
            case 'refusal':
                return ['type' => 'refusal', 'refusal' => $aBlock['refusal'] ?? ''];
            default:
                return ['type' => 'text', 'text' => $aBlock['text'] ?? ''];
        }
    }

    /**
     * Translates the source citations of a text block into the neutral
     * annotations format. Unknown annotation types are passed through unchanged.
     *
     * @param array $aAnnotations Annotations from an output_text block.
     * @return array List of normalized annotations.
     */
    private function normalizeAnnotations(array $aAnnotations): array
    {
        $aResult = [];
        foreach ($aAnnotations as $aAnnotation) {
            switch ($aAnnotation['type'] ?? '') {
                case 'url_citation':
                    $aResult[] = [
                        'type'        => 'url_citation',
                        'url'         => $aAnnotation['url'] ?? '',
                        'title'       => $aAnnotation['title'] ?? '',
                        'start_index' => $aAnnotation['start_index'] ?? 0,
                        'end_index'   => $aAnnotation['end_index'] ?? 0,
                    ];
                    break;
                case 'container_file_citation':
                    $aResult[] = [
                        'type'      => 'file_citation',
                        'file_id'   => $aAnnotation['file_id'] ?? '',
                        'file_name' => $aAnnotation['filename'] ?? '',
                    ];
                    break;
                default:
                    $aResult[] = $aAnnotation;
            }
        }
        return $aResult;
    }

    /**
     * Normalizes the result of an image_generation_call. The tool returns
     * either a data: URI or a URL depending on the model — for the data: URI
     * base64 data and image format are split so the response has both separately.
     *
     * @param string $sResult Result of the tool call, data: URI or URL.
     * @param string $sStatus Status of the tool call.
     * @return array Normalized image output.
     */
    private function normalizeImageResult(string $sResult, string $sStatus): array
    {
        if (str_starts_with($sResult, 'data:')) {
            preg_match('/^data:([^;]+);base64,(.+)$/s', $sResult, $aMatches);
            $sFormat = explode('/', $aMatches[1] ?? 'image/png')[1] ?? 'png';
            return [
                'type'         => 'image',
                'image_data'   => $aMatches[2] ?? $sResult,
                'image_format' => $sFormat,
                'status'       => $sStatus,
            ];
        }
        return ['type' => 'image', 'image_url' => $sResult, 'status' => $sStatus];
    }

    /**
     * Translates the raw Images API answer into the neutral format for
     * ImageResponse.
     *
     * @param array $aRaw Decoded JSON answer of the Images API.
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors.
     */
    private function normalizeImageResponse(array $aRaw): array
    {
        $aOutputs = [];
        foreach ($aRaw['data'] ?? [] as $aItem) {
            $aOutputs[] = [
                'image_data'   => $aItem['b64_json'] ?? '',
                'image_format' => $aRaw['output_format'] ?? 'png',
                'size'         => $aRaw['size'] ?? '',
            ];
        }

        return [
            'model'         => $aRaw['model'] ?? '',
            'input_tokens'  => $aRaw['usage']['input_tokens'] ?? 0,
            'output_tokens' => $aRaw['usage']['output_tokens'] ?? 0,
            'outputs'       => $aOutputs,
            'errors'        => [],
        ];
    }
}
