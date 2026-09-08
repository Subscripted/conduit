<?php

namespace Conduit\Adapter;

use Conduit\Contract\LLMAdapter;
use Conduit\Exception\ApiException;
use Conduit\Exception\TransportException;

/**
 * Base for every provider adapter (OpenAI, Anthropic).
 *
 * Handles the HTTP part that is the same for all providers: a cURL request
 * with a JSON body, timeouts and retries on transient failures. Each
 * provider adapter only adds its own request body, its headers and the
 * normalization of the response on top of this.
 *
 * The endpoints catch the ConduitException thrown here in call() and return
 * a response object with hasErrors() === true instead.
 */
abstract class AbstractLLMAdapter implements LLMAdapter
{
    /** Requests with server tools (web search / web fetch) regularly run over a minute. */
    private const REQUEST_TIMEOUT = 300;
    private const CONNECT_TIMEOUT = 15;

    /**
     * Overload / rate limit on the provider side (529 overloaded_error, 429) and
     * short-lived server errors are transient — retry with growing back-off.
     */
    private const MAX_ATTEMPTS     = 3;
    private const RETRY_HTTP_CODES = [429, 500, 502, 503, 529];

    /**
     * Provider-specific HTTP headers (mostly authentication).
     * Content-Type is set centrally in buildHeaders() and may be omitted here.
     *
     * @return array Headers as ['Name' => 'Value'].
     */
    abstract protected function headers(): array;

    /**
     * Sends a JSON request to the provider and returns the decoded response.
     *
     * On HTTP codes from RETRY_HTTP_CODES the call is retried up to
     * MAX_ATTEMPTS times, the wait doubling each attempt (2, 4, 8 seconds).
     *
     * @param string $sUrl     Full endpoint URL.
     * @param array  $aPayload Request body, sent as JSON.
     * @return array Decoded JSON response of the provider.
     * @throws TransportException When the provider was never reached (cURL error).
     * @throws ApiException When the provider answered with an HTTP error that is not (or no longer) retried.
     */
    protected function request(string $sUrl, array $aPayload): array
    {
        $sJson    = json_encode($aPayload);
        $aDecoded = [];
        $iAttempt = 0;

        while (true) {
            $iAttempt++;
            $oCurl = curl_init($sUrl);

            curl_setopt_array($oCurl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $sJson,
                CURLOPT_HTTPHEADER     => $this->buildHeaders(),
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT        => self::REQUEST_TIMEOUT,
            ]);

            $sResponse  = curl_exec($oCurl);
            $iHttpCode  = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
            $sCurlError = curl_error($oCurl);

            if ($sCurlError) {
                throw new TransportException("cURL error: {$sCurlError}");
            }

            $aDecoded = json_decode($sResponse, true);

            if ($iHttpCode >= 400) {
                $bRetry = in_array($iHttpCode, self::RETRY_HTTP_CODES, true)
                    && $iAttempt < self::MAX_ATTEMPTS;

                if (!$bRetry) {
                    throw ApiException::fromResponse(
                        $iHttpCode,
                        (string) $sResponse,
                        is_array($aDecoded) ? $aDecoded : null,
                    );
                }

                sleep(2 ** $iAttempt);
                continue;
            }

            break;
        }

        return $aDecoded;
    }

    /**
     * Brings the headers from headers() into the 'Name: Value' format cURL expects.
     *
     * @return string[] Header lines including the shared Content-Type.
     */
    private function buildHeaders(): array
    {
        $aFormatted = ['Content-Type: application/json'];
        foreach ($this->headers() as $sKey => $sValue) {
            $aFormatted[] = "{$sKey}: {$sValue}";
        }
        return $aFormatted;
    }
}
