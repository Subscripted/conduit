<?php

namespace Conduit\Adapter;

use Conduit\Configuration\ConduitConfig;
use Conduit\Contract\LLMAdapter;
use Conduit\Exception\ApiException;
use Conduit\Exception\TransportException;

/**
 * Base for every provider adapter (OpenAI, Anthropic).
 *
 * Handles the HTTP part that is the same for all providers: a cURL request
 * with a JSON body, timeouts and retries on transient failures, all driven
 * by the injected ConduitConfig rather than hardcoded constants. Each
 * provider adapter only adds its own request body, its headers and the
 * normalization of the response on top of this.
 *
 * The endpoints catch the ConduitException thrown here in call() and return
 * a response object with hasErrors() === true instead.
 */
abstract class AbstractLLMAdapter implements LLMAdapter
{
    /**
     * @param string        $sApiKey API key of the provider account.
     * @param ConduitConfig $oConfig Timeouts, retry policy and warnings toggle for this client.
     */
    public function __construct(protected readonly string $sApiKey, protected readonly ConduitConfig $oConfig)
    {
    }

    /**
     * Warnings collected while building the current request — reset at the
     * start of chat()/image() via resetWarnings(), read back into the
     * normalized response's 'warnings' key at the end.
     *
     * @var string[]
     */
    private array $aWarnings = [];

    /**
     * Records that some part of the neutral payload couldn't be translated
     * for this provider (an unsupported tool type, a malformed tool
     * definition, ...) instead of silently dropping it without a trace.
     * A no-op when the config disabled warnings.
     *
     * @param string $sMessage Human-readable reason, surfaced via getWarnings() on the response.
     */
    protected function warn(string $sMessage): void
    {
        if ($this->oConfig->isWarningsEnabled()) {
            $this->aWarnings[] = $sMessage;
        }
    }

    /**
     * Clears warnings from a previous request. Call at the start of
     * chat()/image() — defensive: adapters are built fresh per call today,
     * but this keeps warn() safe if that ever changes.
     */
    protected function resetWarnings(): void
    {
        $this->aWarnings = [];
    }

    /**
     * @return string[] Warnings collected while building the current request.
     */
    protected function getWarnings(): array
    {
        return $this->aWarnings;
    }

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
     * On HTTP codes from the config's retryHttpCodes the call is retried up
     * to maxAttempts times, the wait doubling each attempt (2, 4, 8 seconds).
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
                CURLOPT_CONNECTTIMEOUT => $this->oConfig->getConnectTimeout(),
                CURLOPT_TIMEOUT        => $this->oConfig->getRequestTimeout(),
            ]);

            $sResponse  = curl_exec($oCurl);
            $iHttpCode  = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
            $sCurlError = curl_error($oCurl);

            if ($sCurlError) {
                throw new TransportException("cURL error: {$sCurlError}");
            }

            $aDecoded = json_decode($sResponse, true);

            if ($iHttpCode >= 400) {
                $bRetry = in_array($iHttpCode, $this->oConfig->getRetryHttpCodes(), true)
                    && $iAttempt < $this->oConfig->getMaxAttempts();

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
