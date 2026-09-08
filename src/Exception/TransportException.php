<?php

namespace Conduit\Exception;

use RuntimeException;

/**
 * The HTTP request never produced a response: DNS failure, connection
 * refused, TLS error, timeout. The provider was never reached, so a retry
 * with the same input is reasonable.
 */
class TransportException extends RuntimeException implements ConduitException
{
}
