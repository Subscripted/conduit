<?php

use traits\HasErrors;

/**
 * Base for every response object (ChatResponse, ImageResponse).
 *
 * Holds the fields that every request produces: model, token usage, the
 * individual model outputs and any errors. Data arrives as the normalized
 * array from the adapter; hydrateFromArray() fills it, the concrete class
 * decides in hydrateOutput() which Output class the entries become.
 *
 * Errors do not throw, they land in $aErrors — always check hasErrors()
 * before evaluating the outputs.
 */
abstract class AbstractAIResponse implements Castable, Hydratable, ErrorCollectionInterface
{
    use HasErrors;

    /** @var Output[] */
    protected array  $aOutputs      = [];
    protected string $sModel        = '';
    protected int    $iInputTokens  = 0;
    protected int    $iOutputTokens = 0;

    // ── Getters ───────────────────────────────────────────────────────────

    /**
     * All model outputs in the order they were delivered
     * (text, function calls, web searches, thinking, ...).
     *
     * @return Output[]
     */
    public function getOutputs(): array    { return $this->aOutputs; }

    /** @return string Model that actually answered the request (per the provider response). */
    public function getModel(): string     { return $this->sModel; }

    /** @return int Input tokens consumed (prompt). */
    public function getInputTokens(): int  { return $this->iInputTokens; }

    /** @return int Output tokens consumed (answer incl. thinking). */
    public function getOutputTokens(): int { return $this->iOutputTokens; }

    // ── Hydration ─────────────────────────────────────────────────────────

    /**
     * Fills the shared fields from the normalized adapter array. Called by
     * the fromArray() implementation of each concrete response class.
     *
     * @param array $aData Normalized response: model, input_tokens, output_tokens, outputs, errors.
     */
    protected function hydrateFromArray(array $aData): void
    {
        $this->sModel        = $aData['model'] ?? '';
        $this->iInputTokens  = $aData['input_tokens'] ?? 0;
        $this->iOutputTokens = $aData['output_tokens'] ?? 0;
        $this->aErrors       = $aData['errors'] ?? [];
        foreach ($aData['outputs'] ?? [] as $aOutputData) {
            $this->aOutputs[] = $this->hydrateOutput($aOutputData);
        }
    }

    /**
     * Builds a single output item from the normalized adapter array. Each
     * concrete response class decides which Output class is used for it.
     *
     * @param array $aOutputData One entry from the adapter's outputs array.
     * @return Output Concrete Output class of the calling response (ChatOutput or ImageOutput).
     */
    abstract protected function hydrateOutput(array $aOutputData): Output;

    /**
     * Builds an empty response that only carries the error. Used by the
     * endpoints when the adapter throws a RuntimeException — the caller then
     * gets an object with hasErrors() === true instead of an exception.
     *
     * @param string $sMessage Error message.
     * @return static Response object of the calling class.
     */
    public static function fromError(string $sMessage): static
    {
        $oInstance = new static();
        $oInstance->addError($sMessage);
        return $oInstance;
    }

    // ── Casting ───────────────────────────────────────────────────────────

    /**
     * Returns the whole response as an array (e.g. for json_encode or logging).
     *
     * @return array model, input_tokens, output_tokens, outputs, errors.
     */
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
}
