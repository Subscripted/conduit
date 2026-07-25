<?php

namespace entity\dto;

use Castable;
use Hydratable;
use Returnable;

class ChatResponse implements Castable, Returnable, Hydratable
{
    /** @var ChatOutput[] */
    private array  $aOutputs      = [];
    private string $sModel        = '';
    private int    $iInputTokens  = 0;
    private int    $iOutputTokens = 0;
    private array  $aErrors       = [];

    // ── Getters ───────────────────────────────────────────────────────────

    /** @return ChatOutput[] */
    public function getOutputs(): array     { return $this->aOutputs; }
    public function getModel(): string      { return $this->sModel; }
    public function getInputTokens(): int   { return $this->iInputTokens; }
    public function getOutputTokens(): int  { return $this->iOutputTokens; }
    public function getErrors(): array      { return $this->aErrors; }
    public function hasErrors(): bool       { return !empty($this->aErrors); }

    // ── Convenience accessors ─────────────────────────────────────────────

    public function getText(): string
    {
        foreach ($this->aOutputs as $oOutput) {
            if ($oOutput->isText()) {
                return $oOutput->getText();
            }
        }
        return '';
    }

    /** @return ChatOutput[] */
    public function getTexts(): array
    {
        $aResult = [];
        foreach ($this->aOutputs as $oOutput) {
            if ($oOutput->isText()) {
                $aResult[] = $oOutput;
            }
        }
        return $aResult;
    }

    /** @return ChatOutput[] */
    public function getFunctionCalls(): array
    {
        $aResult = [];
        foreach ($this->aOutputs as $oOutput) {
            if ($oOutput->isFunctionCall()) {
                $aResult[] = $oOutput;
            }
        }
        return $aResult;
    }

    /** @return ChatOutput[] */
    public function getImages(): array
    {
        $aResult = [];
        foreach ($this->aOutputs as $oOutput) {
            if ($oOutput->isImage()) {
                $aResult[] = $oOutput;
            }
        }
        return $aResult;
    }

    /** @return ChatOutput[] */
    public function getWebSearches(): array
    {
        $aResult = [];
        foreach ($this->aOutputs as $oOutput) {
            if ($oOutput->isWebSearch()) {
                $aResult[] = $oOutput;
            }
        }
        return $aResult;
    }

    // ── Hydration ─────────────────────────────────────────────────────────

    public static function fromArray(array $aData): self
    {
        $oInstance                = new self();
        $oInstance->sModel        = $aData['model'] ?? '';
        $oInstance->iInputTokens  = $aData['input_tokens'] ?? 0;
        $oInstance->iOutputTokens = $aData['output_tokens'] ?? 0;
        $oInstance->aErrors       = $aData['errors'] ?? [];
        foreach ($aData['outputs'] ?? [] as $aOutputData) {
            $oInstance->aOutputs[] = ChatOutput::fromArray($aOutputData);
        }
        return $oInstance;
    }

    public static function error(string $sMessage): self
    {
        $oInstance            = new self();
        $oInstance->aErrors[] = $sMessage;
        return $oInstance;
    }

    // ── Casting ───────────────────────────────────────────────────────────

    public function __toArray(): array
    {
        $aOutputs = [];
        foreach ($this->aOutputs as $oOutput) {
            $aOutputs[] = $oOutput->__toArray();
        }
        return [
            'model'         => $this->sModel,
            'input_tokens'  => $this->iInputTokens,
            'output_tokens' => $this->iOutputTokens,
            'outputs'       => $aOutputs,
            'errors'        => $this->aErrors,
        ];
    }

    public function __toString(): string
    {
        return $this->getText();
    }
}
