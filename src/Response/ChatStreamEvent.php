<?php

namespace Conduit\Response;

use Conduit\Contract\Castable;
use Conduit\Contract\Hydratable;
use Conduit\Enum\OutputType;
use Conduit\Enum\StreamEventType;
use Throwable;

/**
 * A single event yielded by Chat::stream().
 *
 * Not a Contract\Output — that interface documents itself as a block of
 * model content, while this is an envelope over one chunk of the stream.
 * Which fields are filled depends on getType():
 *   - OutputStart:  getIndex(), getOutputType()
 *   - *Delta:       getIndex(), getDelta()
 *   - OutputDone:   getIndex(), getOutput() — a normal ChatOutput, the same
 *                   class and shape Chat::call() already returns, so a
 *                   finished streamed block needs no separate class.
 *   - ResponseDone: getModel(), getInputTokens(), getOutputTokens(), getWarnings()
 *   - Error:        getError()
 */
class ChatStreamEvent implements Castable, Hydratable
{
    private StreamEventType $oType;
    private int             $iIndex        = 0;
    private string          $sDelta        = '';
    private ?OutputType     $oOutputType   = null;
    private ?ChatOutput     $oOutput       = null;
    private string          $sModel        = '';
    private int             $iInputTokens  = 0;
    private int             $iOutputTokens = 0;
    private array           $aWarnings     = [];
    private ?Throwable      $oError        = null;

    // ── Getters ───────────────────────────────────────────────────────────

    /** @return StreamEventType Kind of this event. */
    public function getType(): StreamEventType { return $this->oType; }

    /** @return int Index of the output block this event belongs to (0 for response-level events). */
    public function getIndex(): int { return $this->iIndex; }

    /** @return string Incremental fragment, for OutputStart's *Delta events only. */
    public function getDelta(): string { return $this->sDelta; }

    /** @return OutputType|null Kind of the block that just started (OutputStart only, when the provider announced it upfront). */
    public function getOutputType(): ?OutputType { return $this->oOutputType; }

    /** @return ChatOutput|null The finished block (OutputDone only) — same class Chat::call() returns. */
    public function getOutput(): ?ChatOutput { return $this->oOutput; }

    /** @return string Model that answered (ResponseDone only). */
    public function getModel(): string { return $this->sModel; }

    /** @return int Input tokens consumed (ResponseDone only). */
    public function getInputTokens(): int { return $this->iInputTokens; }

    /** @return int Output tokens consumed (ResponseDone only). */
    public function getOutputTokens(): int { return $this->iOutputTokens; }

    /** @return string[] Warnings collected over the whole stream (ResponseDone only). */
    public function getWarnings(): array { return $this->aWarnings; }

    /** @return Throwable|null The failure that ended the stream (Error only). */
    public function getError(): ?Throwable { return $this->oError; }

    // ── Type checks ───────────────────────────────────────────────────────

    public function isOutputStart(): bool { return $this->oType === StreamEventType::OutputStart; }
    public function isTextDelta(): bool { return $this->oType === StreamEventType::TextDelta; }
    public function isThinkingDelta(): bool { return $this->oType === StreamEventType::ThinkingDelta; }
    public function isRefusalDelta(): bool { return $this->oType === StreamEventType::RefusalDelta; }
    public function isFunctionCallArgumentsDelta(): bool { return $this->oType === StreamEventType::FunctionCallArgumentsDelta; }
    public function isOutputDone(): bool { return $this->oType === StreamEventType::OutputDone; }
    public function isResponseDone(): bool { return $this->oType === StreamEventType::ResponseDone; }
    public function isError(): bool { return $this->oType === StreamEventType::Error; }

    // ── Hydration ─────────────────────────────────────────────────────────

    /**
     * Builds an event from the normalized array an adapter's streamChat()
     * (or Chat::stream() itself, for a caught ConduitException) yields.
     *
     * @param array $aData One neutral stream event; 'type' is required.
     * @return self
     * @throws \ValueError If 'type' is not a known StreamEventType.
     */
    public static function fromArray(array $aData): self
    {
        $oInstance               = new self();
        $oInstance->oType        = StreamEventType::from($aData['type']);
        $oInstance->iIndex       = $aData['index'] ?? 0;
        $oInstance->sDelta       = $aData['delta'] ?? '';
        $oInstance->oOutputType  = isset($aData['output_type']) ? OutputType::tryFrom($aData['output_type']) : null;
        $oInstance->oOutput      = isset($aData['output']) ? ChatOutput::fromArray($aData['output']) : null;
        $oInstance->sModel       = $aData['model'] ?? '';
        $oInstance->iInputTokens = $aData['input_tokens'] ?? 0;
        $oInstance->iOutputTokens = $aData['output_tokens'] ?? 0;
        $oInstance->aWarnings    = $aData['warnings'] ?? [];
        $oInstance->oError       = $aData['error'] ?? null;
        return $oInstance;
    }

    // ── Casting ───────────────────────────────────────────────────────────

    /**
     * @return array Always contains 'type', the rest depends on the event kind.
     */
    public function __toArray(): array
    {
        $aResult = ['type' => $this->oType->value, 'index' => $this->iIndex];
        if ($this->sDelta !== '')        $aResult['delta']        = $this->sDelta;
        if ($this->oOutputType !== null) $aResult['output_type']  = $this->oOutputType->value;
        if ($this->oOutput !== null)     $aResult['output']       = $this->oOutput->__toArray();
        if ($this->sModel !== '')        $aResult['model']        = $this->sModel;
        if ($this->iInputTokens !== 0)   $aResult['input_tokens'] = $this->iInputTokens;
        if ($this->iOutputTokens !== 0)  $aResult['output_tokens'] = $this->iOutputTokens;
        if ($this->aWarnings !== [])     $aResult['warnings']     = $this->aWarnings;
        if ($this->oError !== null)      $aResult['error']        = $this->oError->getMessage();
        return $aResult;
    }

    /**
     * @return string The delta fragment, the finished output's text, or empty.
     */
    public function __toString(): string
    {
        return match (true) {
            $this->sDelta !== ''    => $this->sDelta,
            $this->oOutput !== null => (string) $this->oOutput,
            default                 => '',
        };
    }
}
