<?php

namespace traits;

/**
 * Error collection for classes that should not throw exceptions.
 *
 * Direct implementation of ErrorCollectionInterface to avoid redundancy.
 * Used by the response objects: an error does not abort the flow, it lands
 * in the collection and is checked by the caller.
 */
trait HasErrors
{
    protected array $aErrors = [];

    /**
     * @return string[] Error messages as strings, empty when nothing failed.
     */
    public function getErrors(): array
    {
        return $this->aErrors;
    }

    /**
     * Appends an error to this instance's collection.
     *
     * @param string $sError Error message.
     * @return static The instance itself, so calls can be chained.
     */
    public function addError(string $sError): static
    {
        $this->aErrors[] = $sError;
        return $this;
    }

    /**
     * @return bool True when at least one error was collected.
     */
    public function hasErrors(): bool
    {
        return !empty($this->aErrors);
    }
}
