<?php

namespace Conduit\Exception;

use InvalidArgumentException;

/**
 * The client was used before it was fully set up: an empty API key, or a
 * model id whose provider AIProvider::fromModel() can't determine. A
 * programmer error — fix the call site, do not catch this at runtime.
 */
class ConfigurationException extends InvalidArgumentException implements ConduitException
{
}
