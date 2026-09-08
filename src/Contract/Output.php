<?php

namespace Conduit\Contract;

/**
 * Marker interface for every output object (ChatOutput, ImageOutput).
 *
 * Only guarantees the shared contract (Castable, Hydratable) without forcing
 * a common inheritance hierarchy — ChatOutput and ImageOutput are different
 * things that only happen to share some fields (see the HasImageData trait).
 */
interface Output extends Castable, Hydratable
{
}
