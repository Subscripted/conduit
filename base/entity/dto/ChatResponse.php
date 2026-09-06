<?php

namespace entity\dto;

use AbstractAIResponse;

/**
 * Response of a text request.
 *
 * Not created directly — built by Chat::call() from the normalized adapter
 * array. Besides model, token usage and errors (from AbstractAIResponse) it
 * holds every output block of the model as ChatOutput objects.
 *
 * With server tools enabled (web search, web fetch) the provider often
 * splits the answer across several text blocks — getText() then returns
 * only the first, use getOutputs() / getTexts() for the rest.
 */
class ChatResponse extends AbstractAIResponse
{
    // ── Convenience accessors ─────────────────────────────────────────────

    /**
     * All output blocks in the order the model delivered them.
     *
     * @return ChatOutput[]
     */
    public function getOutputs(): array { return parent::getOutputs(); }

    /**
     * @return string Text of the first text block, empty when the response has none.
     */
    public function getText(): string
    {
        foreach ($this->aOutputs as $oOutput) {
            if ($oOutput instanceof ChatOutput && $oOutput->isText()) {
                return $oOutput->getText();
            }
        }
        return '';
    }

    /**
     * All text blocks of the response — the objects, not their strings
     * (ChatOutput has a __toString(), so implode() still yields the text).
     *
     * @return ChatOutput[]
     */
    public function getTexts(): array
    {
        $aResult = [];
        foreach ($this->aOutputs as $oOutput) {
            if ($oOutput instanceof ChatOutput && $oOutput->isText()) {
                $aResult[] = $oOutput;
            }
        }
        return $aResult;
    }

    /**
     * Calls to custom functions the model requested (Tool::function()).
     *
     * @return ChatOutput[]
     */
    public function getFunctionCalls(): array
    {
        $aResult = [];
        foreach ($this->aOutputs as $oOutput) {
            if ($oOutput instanceof ChatOutput && $oOutput->isFunctionCall()) {
                $aResult[] = $oOutput;
            }
        }
        return $aResult;
    }

    /**
     * Images produced via the image_generation tool inside the chat.
     *
     * @return ChatOutput[]
     */
    public function getImages(): array
    {
        $aResult = [];
        foreach ($this->aOutputs as $oOutput) {
            if ($oOutput instanceof ChatOutput && $oOutput->isImage()) {
                $aResult[] = $oOutput;
            }
        }
        return $aResult;
    }

    /**
     * Web searches the provider ran for this response.
     *
     * @return ChatOutput[]
     */
    public function getWebSearches(): array
    {
        $aResult = [];
        foreach ($this->aOutputs as $oOutput) {
            if ($oOutput instanceof ChatOutput && $oOutput->isWebSearch()) {
                $aResult[] = $oOutput;
            }
        }
        return $aResult;
    }

    // ── Hydration ─────────────────────────────────────────────────────────

    /**
     * Builds the response from the normalized adapter array.
     *
     * @param array $aData Normalized response: model, input_tokens, output_tokens, outputs, errors.
     * @return self
     */
    public static function fromArray(array $aData): self
    {
        $oInstance = new self();
        $oInstance->hydrateFromArray($aData);
        return $oInstance;
    }

    /**
     * Maps a single output block to a ChatOutput.
     *
     * @param array $aOutputData One entry from the adapter's outputs array.
     * @return ChatOutput
     */
    protected function hydrateOutput(array $aOutputData): ChatOutput
    {
        return ChatOutput::fromArray($aOutputData);
    }

    // ── Casting ───────────────────────────────────────────────────────────

    /**
     * @return string Text of the first text block, so the response can be echoed directly.
     */
    public function __toString(): string
    {
        return $this->getText();
    }
}
