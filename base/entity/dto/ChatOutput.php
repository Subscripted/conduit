<?php

namespace entity\dto;

use Castable;
use Hydratable;
use type\OutputType;

class ChatOutput implements Castable, Hydratable
{
    private OutputType $oType;

    private string $sText        = '';
    private array  $aAnnotations = [];

    private string $sName      = '';
    private string $sCallId    = '';
    private string $sArguments = '';

    private string $sImageData   = '';
    private string $sImageFormat = 'png';
    private string $sImageUrl    = '';

    private string $sStatus  = '';
    private string $sRefusal = '';
    private string $sThinking = '';

    // ── Getters ───────────────────────────────────────────────────────────

    public function getType(): OutputType  { return $this->oType; }
    public function getText(): string       { return $this->sText; }
    public function getAnnotations(): array { return $this->aAnnotations; }
    public function getName(): string       { return $this->sName; }
    public function getCallId(): string     { return $this->sCallId; }
    public function getArguments(): array   { return json_decode($this->sArguments, true) ?? []; }
    public function getArgumentsRaw(): string { return $this->sArguments; }
    public function getImageData(): string  { return $this->sImageData; }
    public function getImageFormat(): string { return $this->sImageFormat; }
    public function getImageUrl(): string   { return $this->sImageUrl; }
    public function getStatus(): string     { return $this->sStatus; }
    public function getRefusal(): string    { return $this->sRefusal; }
    public function getThinking(): string   { return $this->sThinking; }

    // ── Type checks ───────────────────────────────────────────────────────

    public function isText(): bool         { return $this->oType === OutputType::Text; }
    public function isFunctionCall(): bool { return $this->oType === OutputType::FunctionCall; }
    public function isWebSearch(): bool    { return $this->oType === OutputType::WebSearch; }
    public function isImage(): bool        { return $this->oType === OutputType::Image; }
    public function isRefusal(): bool      { return $this->oType === OutputType::Refusal; }
    public function isThinking(): bool     { return $this->oType === OutputType::Thinking; }

    // ── Hydration ─────────────────────────────────────────────────────────

    public static function fromArray(array $aData): self
    {
        $oInstance               = new self();
        $oInstance->oType        = OutputType::from($aData['type']);
        $oInstance->sText        = $aData['text'] ?? '';
        $oInstance->aAnnotations = $aData['annotations'] ?? [];
        $oInstance->sName        = $aData['name'] ?? '';
        $oInstance->sCallId      = $aData['call_id'] ?? '';
        $oInstance->sArguments   = $aData['arguments'] ?? '';
        $oInstance->sImageData   = $aData['image_data'] ?? '';
        $oInstance->sImageFormat = $aData['image_format'] ?? 'png';
        $oInstance->sImageUrl    = $aData['image_url'] ?? '';
        $oInstance->sStatus      = $aData['status'] ?? '';
        $oInstance->sRefusal     = $aData['refusal'] ?? '';
        $oInstance->sThinking    = $aData['thinking'] ?? '';
        return $oInstance;
    }

    // ── Casting ───────────────────────────────────────────────────────────

    public function __toArray(): array
    {
        $aResult = ['type' => $this->oType->value];
        if ($this->sText !== '')         $aResult['text']         = $this->sText;
        if ($this->aAnnotations !== [])  $aResult['annotations']  = $this->aAnnotations;
        if ($this->sName !== '')         $aResult['name']         = $this->sName;
        if ($this->sCallId !== '')       $aResult['call_id']      = $this->sCallId;
        if ($this->sArguments !== '')    $aResult['arguments']    = $this->sArguments;
        if ($this->sImageData !== '')  { $aResult['image_data']   = $this->sImageData;
                                         $aResult['image_format'] = $this->sImageFormat; }
        if ($this->sImageUrl !== '')     $aResult['image_url']    = $this->sImageUrl;
        if ($this->sStatus !== '')       $aResult['status']       = $this->sStatus;
        if ($this->sRefusal !== '')      $aResult['refusal']      = $this->sRefusal;
        if ($this->sThinking !== '')     $aResult['thinking']     = $this->sThinking;
        return $aResult;
    }

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
