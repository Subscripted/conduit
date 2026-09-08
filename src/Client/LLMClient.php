<?php

namespace Conduit\Client;

use Conduit\Endpoint\Chat;
use Conduit\Endpoint\Image;
use Conduit\Enum\AIProvider;
use Conduit\Exception\ConfigurationException;

/**
 * Entry point for every AI request.
 *
 * Holds the API key and the selected provider. Through chat() / image() it
 * hands out the matching endpoint, which is then filled with fluent setters
 * and sent with call().
 *
 * Without a provider set beforehand, any access to an endpoint throws a
 * ConfigurationException.
 */
class LLMClient
{
    private AIProvider $oProvider;

    /**
     * @param string $sApiKey API key used for whichever provider is active.
     * @throws ConfigurationException If the key is empty.
     */
    public function __construct(private readonly string $sApiKey)
    {
        if (empty($sApiKey)) {
            throw new ConfigurationException('API key may not be empty');
        }
    }

    /**
     * Returns the endpoint for text requests.
     *
     * @return Chat A fresh Chat endpoint, filled via fluent setters.
     * @throws ConfigurationException If no AIProvider has been set yet.
     */
    public function chat(): Chat
    {
        $this->assertProviderSet();
        return new Chat($this->sApiKey, $this);
    }

    /**
     * Returns the endpoint for image generation.
     *
     * @return Image A fresh Image endpoint, filled via fluent setters.
     * @throws ConfigurationException If no AIProvider has been set yet.
     */
    public function image(): Image
    {
        $this->assertProviderSet();
        return new Image($this->sApiKey, $this);
    }

    /**
     * Sets the provider whose adapter the endpoints will use.
     *
     * @param AIProvider $oProvider Provider (OpenAI, Anthropic, ...).
     * @return self
     */
    public function setAIProvider(AIProvider $oProvider): self
    {
        $this->oProvider = $oProvider;
        return $this;
    }

    /**
     * @return AIProvider Currently set provider.
     * @throws ConfigurationException If no AIProvider has been set yet.
     */
    public function getAIProvider(): AIProvider
    {
        $this->assertProviderSet();
        return $this->oProvider;
    }

    /**
     * Ensures setAIProvider() was called before an endpoint is used.
     *
     * @throws ConfigurationException If no AIProvider has been set yet.
     */
    private function assertProviderSet(): void
    {
        if (!isset($this->oProvider)) {
            throw new ConfigurationException('No AIProvider set. Call setAIProvider(AIProvider::...) first.');
        }
    }
}
