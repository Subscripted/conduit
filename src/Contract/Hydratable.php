<?php

namespace Conduit\Contract;

/**
 * Counterpart to Castable: the adapters return their answer as a normalized
 * array, fromArray() turns it into the concrete object. This keeps the
 * adapter field names known in exactly one place per class.
 */
interface Hydratable
{
    /**
     * Builds an instance from a normalized adapter array.
     *
     * @param array $aData Normalized array coming from the adapter.
     * @return self
     */
    public static function fromArray(array $aData): self;
}
