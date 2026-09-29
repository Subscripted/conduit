<?php

namespace Conduit\Contract;

/**
 * Contract for classes that collect non-fatal warnings instead of just
 * silently dropping something.
 *
 * Implemented via the HasWarnings trait and used by the response objects: an
 * adapter that can't translate part of the neutral payload for its provider
 * (an unsupported tool type, a tool that couldn't be built, ...) records why
 * here instead of the caller only finding out by noticing the feature never
 * ran. Unlike ErrorCollectionInterface this never means the request failed —
 * a response can carry warnings and still be a normal, usable answer.
 */
interface WarningCollectionInterface
{
    /**
     * @return string[] Collected warning messages, empty when nothing was dropped or degraded.
     */
    public function getWarnings(): array;

    /**
     * @return bool True when at least one warning was collected.
     */
    public function hasWarnings(): bool;

    /**
     * Appends a warning to this instance's collection.
     *
     * @param string $sWarning The warning message to record.
     * @return static The instance itself, so calls can be chained.
     */
    public function addWarning(string $sWarning): static;
}
