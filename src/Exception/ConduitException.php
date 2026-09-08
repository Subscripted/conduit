<?php

namespace Conduit\Exception;

use Throwable;

/**
 * Marker for every exception the SDK raises.
 *
 * Each concrete exception extends the SPL class that best fits its cause
 * (RuntimeException for runtime failures, InvalidArgumentException for
 * misconfiguration, LogicException for unsupported capabilities) and
 * implements this interface on top. Callers that only want "did Conduit
 * fail?" catch `ConduitException`; callers that need the reason catch the
 * concrete type.
 */
interface ConduitException extends Throwable
{
}
