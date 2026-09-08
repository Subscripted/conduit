<?php

namespace Conduit\Exception;

use RuntimeException;
use Throwable;

/**
 * The provider answered with an HTTP error status (>= 400). Carries the
 * status code and the decoded response body so callers can tell a 401 (bad
 * key) from a 429 (rate limit) from a 400 (malformed request) without
 * parsing the message string.
 */
class ApiException extends RuntimeException implements ConduitException
{
    /**
     * @param string     $sMessage      Human-readable summary (provider message when available).
     * @param int        $iStatusCode   HTTP status the provider returned.
     * @param array|null  $aResponseBody Decoded JSON body, or null when it wasn't JSON.
     * @param Throwable|null $oPrevious   Previous exception, if any.
     */
    public function __construct(
        string $sMessage,
        public readonly int $iStatusCode = 0,
        public readonly ?array $aResponseBody = null,
        ?Throwable $oPrevious = null,
    ) {
        parent::__construct($sMessage, 0, $oPrevious);
    }

    /**
     * Builds the exception from a raw provider error response. Pulls
     * `error.message` out of the decoded body when present, otherwise falls
     * back to the raw payload.
     *
     * @param int        $iStatusCode HTTP status code.
     * @param string     $sRawBody    Response body as received.
     * @param array|null $aDecoded    json_decode() result of $sRawBody, or null.
     * @return self
     */
    public static function fromResponse(int $iStatusCode, string $sRawBody, ?array $aDecoded = null): self
    {
        $sProviderMessage = $aDecoded['error']['message'] ?? $aDecoded['message'] ?? $sRawBody;
        return new self(
            "API error {$iStatusCode}: {$sProviderMessage}",
            $iStatusCode,
            $aDecoded,
        );
    }
}
