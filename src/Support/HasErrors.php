<?php

namespace Conduit\Support;

use Throwable;

/**
 * Error collection for classes that should not throw.
 *
 * Implements Conduit\Contract\ErrorCollectionInterface. Used by the response
 * objects: a provider failure does not abort the flow, the exception lands
 * here whole and is checked by the caller. Keeping the Throwable (rather than
 * just its message) lets callers branch on the concrete type — e.g.
 * Conduit\Exception\ApiException::$statusCode.
 */
trait HasErrors
{
    /** @var Throwable[] */
    protected array $aErrors = [];

    /**
     * @return Throwable[] Collected exceptions, empty when nothing failed.
     */
    public function getErrors(): array
    {
        return $this->aErrors;
    }

    /**
     * @return string[] The message of each collected exception, in order.
     */
    public function getErrorMessages(): array
    {
        return array_map(static fn (Throwable $oError): string => $oError->getMessage(), $this->aErrors);
    }

    /**
     * @return Throwable|null The first collected exception, or null when there is none.
     */
    public function getFirstError(): ?Throwable
    {
        return $this->aErrors[0] ?? null;
    }

    /**
     * Appends an exception to this instance's collection.
     *
     * @param Throwable $oError The failure to record.
     * @return static The instance itself, so calls can be chained.
     */
    public function addError(Throwable $oError): static
    {
        $this->aErrors[] = $oError;
        return $this;
    }

    /**
     * @return bool True when at least one exception was collected.
     */
    public function hasErrors(): bool
    {
        return $this->aErrors !== [];
    }
}
