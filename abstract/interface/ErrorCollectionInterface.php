<?php

/**
 * Contract for classes that collect errors instead of throwing them.
 *
 * Implemented by the HasErrors trait and used by the response objects: an
 * error does not abort the flow, it lands in the collection and is checked
 * by the caller via hasErrors().
 */
interface ErrorCollectionInterface
{
    /**
     * @return string[] Collected error messages, empty when nothing failed.
     */
    public function getErrors(): array;

    /**
     * @return bool True when at least one error was collected.
     */
    public function hasErrors(): bool;

    /**
     * Appends an error to this instance's collection.
     *
     * @param string $sError Error message.
     * @return static The instance itself, so calls can be chained.
     */
    public function addError(string $sError): static;
}
