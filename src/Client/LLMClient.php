<?php

namespace Conduit\Client;

use Conduit\Endpoint\Chat;
use Conduit\Endpoint\Image;
use Conduit\Exception\ConfigurationException;

/**
 * Entry point for every AI request.
 *
 * Just an access point for the endpoints — holds the API key and hands out
 * a fresh Chat or Image endpoint through chat() / image(). Which provider a
 * request goes to is decided later, by AdapterFactory::make() from the model
 * id set on the endpoint; the client itself never picks or resolves one.
 */
class LLMClient
{
    /**
     * @param string $sApiKey API key used for whichever provider the model resolves to.
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
     */
    public function chat(): Chat
    {
        return new Chat($this->sApiKey);
    }

    /**
     * Returns the endpoint for image generation.
     *
     * @return Image A fresh Image endpoint, filled via fluent setters.
     */
    public function image(): Image
    {
        return new Image($this->sApiKey);
    }
}
