<?php

namespace Conduit\Contract;

use Throwable;

/**
 * Contract for classes that collect errors instead of throwing them.
 *
 * Implemented via the HasErrors trait and used by the response objects: a
 * provider failure does not abort the flow, the exception lands in the
 * collection and is checked by the caller via hasErrors(). The exception
 * object is kept whole — getErrors() returns Throwable instances, not strings —
 * so callers can inspect type, status code and previous exception.
 */
interface ErrorCollectionInterface
{
    /**
     * @return Throwable[] Collected exceptions, empty when nothing failed.
     */
    public function getErrors(): array;

    /**
     * @return string[] The message of each collected exception, in order.
     */
    public function getErrorMessages(): array;

    /**
     * @return Throwable|null The first collected exception, or null when there is none.
     */
    public function getFirstError(): ?Throwable;

    /**
     * @return bool True when at least one exception was collected.
     */
    public function hasErrors(): bool;

    /**
     * Appends an exception to this instance's collection.
     *
     * @param Throwable $oError The failure to record.
     * @return static The instance itself, so calls can be chained.
     */
    public function addError(Throwable $oError): static;
}
