<?php

namespace Conduit\Configuration;

/**
 * Cross-cutting SDK behaviour, independent of any single request.
 *
 * Built once per LLMClient instance and passed down to whichever adapter a
 * call resolves to — never a singleton, since two clients (different API
 * keys, different accounts) may well want different retry/timeout policies.
 * default() returns an instance with the same values every adapter used to
 * hardcode; override only what you need via the fluent setters.
 */
class ConduitConfig
{
    /**
     * All parameters are optional and default to the SDK's built-in values —
     * pass only the ones you want to change, by name:
     * `new ConduitConfig(iMaxAttempts: 5, bWarningsEnabled: false)`. The
     * fluent setters below remain available afterwards for further tweaks.
     *
     * @param int $iRequestTimeout Seconds to wait for the provider to answer.
     * @param int $iConnectTimeout Seconds to wait for the TCP connection to establish.
     * @param int $iMaxAttempts Maximum number of attempts (including the first).
     * @param int[] $aRetryHttpCodes HTTP status codes worth retrying.
     * @param bool $bWarningsEnabled Whether adapters should collect warnings at all.
     */
    public function __construct(
        private int   $iRequestTimeout = 300,
        private int   $iConnectTimeout = 15,
        private int   $iMaxAttempts = 3,
        private array $aRetryHttpCodes = [429, 500, 502, 503, 529],
        private bool  $bWarningsEnabled = true){

    }

    /**
     * @return self A config carrying the SDK's built-in defaults.
     */
    public static function default(): self
    {
        return new self();
    }

    // ── Getters ───────────────────────────────────────────────────────────

    /** @return int Seconds to wait for the provider to answer (requests with server tools regularly run over a minute). */
    public function getRequestTimeout(): int
    {
        return $this->iRequestTimeout;
    }

    /** @return int Seconds to wait for the TCP connection to the provider to establish. */
    public function getConnectTimeout(): int
    {
        return $this->iConnectTimeout;
    }

    /** @return int Maximum number of attempts (including the first) before a retryable failure is given up on. */
    public function getMaxAttempts(): int
    {
        return $this->iMaxAttempts;
    }

    /** @return int[] HTTP status codes considered transient and worth retrying (rate limits, overload, short-lived server errors). */
    public function getRetryHttpCodes(): array
    {
        return $this->aRetryHttpCodes;
    }

    /** @return bool Whether adapters collect warnings for unsupported/dropped tools (default true). */
    public function isWarningsEnabled(): bool
    {
        return $this->bWarningsEnabled;
    }

    // ── Setters ───────────────────────────────────────────────────────────

    /**
     * @param int $iSeconds Seconds to wait for the provider to answer.
     * @return self
     */
    public function requestTimeout(int $iSeconds): self
    {
        $this->iRequestTimeout = $iSeconds;
        return $this;
    }

    /**
     * @param int $iSeconds Seconds to wait for the TCP connection to establish.
     * @return self
     */
    public function connectTimeout(int $iSeconds): self
    {
        $this->iConnectTimeout = $iSeconds;
        return $this;
    }

    /**
     * @param int $iMaxAttempts Maximum number of attempts (including the first).
     * @return self
     */
    public function maxAttempts(int $iMaxAttempts): self
    {
        $this->iMaxAttempts = $iMaxAttempts;
        return $this;
    }

    /**
     * @param int[] $aCodes HTTP status codes worth retrying.
     * @return self
     */
    public function retryHttpCodes(array $aCodes): self
    {
        $this->aRetryHttpCodes = $aCodes;
        return $this;
    }

    /**
     * @param bool $bEnabled Whether adapters should collect warnings at all.
     * @return self
     */
    public function warningsEnabled(bool $bEnabled): self
    {
        $this->bWarningsEnabled = $bEnabled;
        return $this;
    }
}
