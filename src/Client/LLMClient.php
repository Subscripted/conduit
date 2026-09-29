<?php

namespace Conduit\Client;

use Conduit\Configuration\ConduitConfig;
use Conduit\Endpoint\Chat;
use Conduit\Endpoint\Image;
use Conduit\Exception\ConfigurationException;

/**
 * Entry point for every AI request.
 *
 * Just an access point for the endpoints — holds the API key and the config,
 * and hands out a fresh Chat or Image endpoint through chat() / image().
 * Which provider a request goes to is decided later, by AdapterFactory::
 * make() from the model id set on the endpoint; the client itself never
 * picks or resolves one.
 *
 * The config is owned by this client instance, not a singleton — two
 * clients (different API keys, different accounts) may well want different
 * retry/timeout policies, so each carries its own ConduitConfig.
 */
readonly class LLMClient
{
    /**
     * @param string        $sApiKey API key used for whichever provider the model resolves to.
     * @param ConduitConfig $oConfig Timeouts, retry policy and warnings toggle for every
     *                               request made through this client. Defaults to
     *                               ConduitConfig::default() when omitted.
     * @throws ConfigurationException If the key is empty.
     */
    public function __construct(private string $sApiKey, private ConduitConfig $oConfig = new ConduitConfig()) {
        if (empty($sApiKey)) {
            throw new ConfigurationException('API key may not be empty');
        }
    }

    /**
     * Returns the endpoint for text requests.
     *
     * @return Chat A fresh Chat endpoint, filled via fluent setters.
     */
    public function chat(): Chat
    {
        return new Chat($this->sApiKey, $this->oConfig);
    }

    /**
     * Returns the endpoint for image generation.
     *
     * @return Image A fresh Image endpoint, filled via fluent setters.
     */
    public function image(): Image
    {
        return new Image($this->sApiKey, $this->oConfig);
    }

    /**
     * Returns the Configuration for the Client.
     *
     * @return ConduitConfig
     */
    public function getConfig(): ConduitConfig {
        return $this->oConfig;
    }
}
