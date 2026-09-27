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
     * OpenAI and Anthropic put a plain string there. Mistral's validation
     * errors instead put a list of `{msg: ...}` objects (or plain strings) in
     * `message` / `detail` — those are joined into one string rather than
     * passed through as an array.
     *
     * @param int        $iStatusCode HTTP status code.
     * @param string     $sRawBody    Response body as received.
     * @param array|null $aDecoded    json_decode() result of $sRawBody, or null.
     * @return self
     */
    public static function fromResponse(int $iStatusCode, string $sRawBody, ?array $aDecoded = null): self
    {
        $mMessage = $aDecoded['error']['message'] ?? $aDecoded['message'] ?? $aDecoded['detail'] ?? null;

        if (is_array($mMessage)) {
            $mMessage = implode('; ', array_filter(array_map(
                static fn ($mEntry) => is_array($mEntry) ? ($mEntry['msg'] ?? null) : (is_string($mEntry) ? $mEntry : null),
                $mMessage,
            )));
        }

        $sProviderMessage = !empty($mMessage) ? $mMessage : $sRawBody;

        return new self(
            "API error {$iStatusCode}: {$sProviderMessage}",
            $iStatusCode,
            $aDecoded,
        );
    }
}
