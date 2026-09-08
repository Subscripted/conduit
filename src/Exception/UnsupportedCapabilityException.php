<?php

namespace Conduit\Exception;

use LogicException;

/**
 * The selected provider cannot serve the requested endpoint — Anthropic has
 * no image API, the Google adapter isn't implemented yet. The endpoints
 * catch this and hand back a response with hasErrors() === true rather than
 * letting it surface.
 */
class UnsupportedCapabilityException extends LogicException implements ConduitException
{
}
