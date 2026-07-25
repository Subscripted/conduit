<?php

namespace adapter;

use AbstractLLMAdapter;

class OpenAIAdapter extends AbstractLLMAdapter
{
    private const BASE_URL = 'https://api.openai.com/v1';

    public function __construct(private readonly string $sApiKey) {}

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->sApiKey];
    }

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
        if (!empty($aPayload['tools'])) {
            $aBuiltTools = $this->buildTools($aPayload['tools']);
            if (!empty($aBuiltTools)) {
                $aBody['tools'] = $aBuiltTools;
            }
        }

        $aRaw = $this->request(self::BASE_URL . '/responses', $aBody);
        return $this->normalizeResponse($aRaw);
    }

    // ── Input builder ─────────────────────────────────────────────────────

    private function buildInput(array $aPayload): array
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
                'type'    => 'function_call_output',
                'call_id' => $aMessage['tool_call_id'],
                'output'  => is_array($mContent) ? json_encode($mContent) : (string) $mContent,
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
                    $aResult[] = ['type' => 'input_text', 'text' => $aBlock['text']];
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

    private function buildTools(array $aTools): array
    {
        $aResult = [];
        foreach ($aTools as $aTool) {
            $sType  = $aTool['_type'] ?? '';
            $aBuilt = null;

            switch ($sType) {
                case 'web_search':
                    $aBuilt = ['type' => 'web_search', 'search_context_size' => $aTool['context_size'] ?? 'medium'];
                    if (!empty($aTool['user_location']))   $aBuilt['user_location'] = $aTool['user_location'];
                    $aFilters = [];
                    if (!empty($aTool['allowed_domains'])) $aFilters['allowed_domains'] = $aTool['allowed_domains'];
                    if (!empty($aTool['blocked_domains'])) $aFilters['blocked_domains'] = $aTool['blocked_domains'];
                    if (!empty($aFilters))                 $aBuilt['filters'] = $aFilters;
                    break;

                case 'function':
                    $aBuilt = [
                        'type'     => 'function',
                        'function' => [
                            'name'        => $aTool['name'],
                            'description' => $aTool['description'],
                            'parameters'  => $aTool['parameters'],
                        ],
                    ];
                    break;

                case 'image_generation':
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

                case 'mcp':
                    $aBuilt = [
                        'type'             => 'mcp',
                        'server_label'     => $aTool['name'],
                        'server_url'       => $aTool['url'],
                        'require_approval' => $aTool['require_approval'] ?? 'always',
                    ];
                    if (!empty($aTool['allowed_tools'])) $aBuilt['allowed_tools'] = $aTool['allowed_tools'];
                    break;
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
}
