<?php

namespace entity\dto;

use Output;
use traits\HasImageData;
use type\OutputType;

/**
 * A single output block of a text response.
 *
 * A response is not one text but a sequence of blocks: text, a call to a
 * custom function, web search, web fetch, image, refusal or thinking. This
 * class represents one such block; which fields are filled depends on the
 * type — check the type before every getter.
 */
class ChatOutput implements Output
{
    use HasImageData;

    private OutputType $oType;

    private string $sText        = '';
    private array  $aAnnotations = [];

    private string $sName      = '';
    private string $sCallId    = '';
    private string $sArguments = '';

    private string $sRefusal  = '';
    private string $sThinking = '';

    // ── Getters ───────────────────────────────────────────────────────────

    /** @return OutputType Kind of the block (Text, FunctionCall, WebSearch, ...). */
    public function getType(): OutputType    { return $this->oType; }

    /** @return string Text of the block, empty for every other type. */
    public function getText(): string        { return $this->sText; }

    /** @return array Source citations the provider adds on web search / web fetch, empty otherwise. */
    public function getAnnotations(): array  { return $this->aAnnotations; }

    /** @return string Name of the function the model wants to call. */
    public function getName(): string        { return $this->sName; }

    /** @return string Id of the function call — needed to answer via Context::tool(). */
    public function getCallId(): string      { return $this->sCallId; }

    /** @return array Arguments of the function call, empty when no valid JSON was delivered. */
    public function getArguments(): array    { return json_decode($this->sArguments, true) ?? []; }

    /** @return string Arguments of the function call unchanged as a JSON string. */
    public function getArgumentsRaw(): string { return $this->sArguments; }

    /** @return string Reason the model gave when it refused to answer. */
    public function getRefusal(): string     { return $this->sRefusal; }

    /** @return string Summary of the reasoning, if requested via effort(..., true). */
    public function getThinking(): string    { return $this->sThinking; }

    // ── Type checks ───────────────────────────────────────────────────────

    /** @return bool True when this block is plain text. */
    public function isText(): bool         { return $this->oType === OutputType::Text; }

    /** @return bool True when the model requested a custom function call. */
    public function isFunctionCall(): bool { return $this->oType === OutputType::FunctionCall; }

    /** @return bool True when this block records a web search the provider ran. */
    public function isWebSearch(): bool    { return $this->oType === OutputType::WebSearch; }

    /** @return bool True when this block records a web fetch the provider ran. */
    public function isWebFetch(): bool     { return $this->oType === OutputType::WebFetch; }

    /** @return bool True when this block carries a generated image. */
    public function isImage(): bool        { return $this->oType === OutputType::Image; }

    /** @return bool True when the model refused to answer. */
    public function isRefusal(): bool      { return $this->oType === OutputType::Refusal; }

    /** @return bool True when this block carries a thinking summary. */
    public function isThinking(): bool     { return $this->oType === OutputType::Thinking; }

    // ── Hydration ─────────────────────────────────────────────────────────

    /**
     * Builds a block from the normalized adapter array.
     *
     * @param array $aData One entry from the adapter's outputs array; 'type' is required.
     * @return self
     * @throws \ValueError If 'type' is not a known OutputType.
     */
    public static function fromArray(array $aData): self
    {
        $oInstance               = new self();
        $oInstance->oType        = OutputType::from($aData['type']);
        $oInstance->sText        = $aData['text'] ?? '';
        $oInstance->aAnnotations = $aData['annotations'] ?? [];
        $oInstance->sName        = $aData['name'] ?? '';
        $oInstance->sCallId      = $aData['call_id'] ?? '';
        $oInstance->sArguments   = $aData['arguments'] ?? '';
        $oInstance->hydrateImageData($aData);
        $oInstance->sRefusal     = $aData['refusal'] ?? '';
        $oInstance->sThinking    = $aData['thinking'] ?? '';
        return $oInstance;
    }

    // ── Casting ───────────────────────────────────────────────────────────

    /**
     * Returns the block as an array, empty fields are left out.
     *
     * @return array Always contains 'type', the rest depends on the block kind.
     */
    public function __toArray(): array
    {
        $aResult = ['type' => $this->oType->value];
        if ($this->sText !== '')        $aResult['text']        = $this->sText;
        if ($this->aAnnotations !== []) $aResult['annotations'] = $this->aAnnotations;
        if ($this->sName !== '')        $aResult['name']        = $this->sName;
        if ($this->sCallId !== '')      $aResult['call_id']     = $this->sCallId;
        if ($this->sArguments !== '')   $aResult['arguments']   = $this->sArguments;
        $aResult = array_merge($aResult, $this->toArrayImageData());
        if ($this->sRefusal !== '')     $aResult['refusal']     = $this->sRefusal;
        if ($this->sThinking !== '')    $aResult['thinking']    = $this->sThinking;
        return $aResult;
    }

    /**
     * @return string Readable content of the block (text, refusal or thinking), otherwise empty.
     */
    public function __toString(): string
    {
        return match ($this->oType) {
            OutputType::Text     => $this->sText,
            OutputType::Refusal  => $this->sRefusal,
            OutputType::Thinking => $this->sThinking,
            default              => '',
        };
    }
}
