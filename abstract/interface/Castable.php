<?php

/**
 * Contract for objects that can turn themselves into an array or a string.
 *
 * Fulfilled by every output object and every response. __toArray() returns
 * the full structure (e.g. for json_encode or logging), __toString() the
 * readable content so the object can be echoed directly.
 */
interface Castable
{
    /**
     * @return array The complete structure of the object.
     */
    public function __toArray(): array;

    /**
     * @return string The readable content of the object.
     */
    public function __toString(): string;
}
