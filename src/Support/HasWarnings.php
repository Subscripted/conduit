<?php

namespace Conduit\Support;

/**
 * Warning collection for classes that should surface a degraded translation
 * instead of just silently dropping it.
 *
 * Implements Conduit\Contract\WarningCollectionInterface. Used by the
 * response objects: an adapter that can't map part of the neutral payload
 * onto its provider (an unsupported tool type, a malformed tool definition,
 * ...) records a plain-text reason here rather than the caller only noticing
 * because the feature never actually ran.
 */
trait HasWarnings
{
    /** @var string[] */
    protected array $aWarnings = [];

    /**
     * @return string[] Collected warning messages, empty when nothing was dropped or degraded.
     */
    public function getWarnings(): array
    {
        return $this->aWarnings;
    }

    /**
     * @return bool True when at least one warning was collected.
     */
    public function hasWarnings(): bool
    {
        return $this->aWarnings !== [];
    }

    /**
     * Appends a warning to this instance's collection.
     *
     * @param string $sWarning The warning message to record.
     * @return static The instance itself, so calls can be chained.
     */
    public function addWarning(string $sWarning): static
    {
        $this->aWarnings[] = $sWarning;
        return $this;
    }
}
