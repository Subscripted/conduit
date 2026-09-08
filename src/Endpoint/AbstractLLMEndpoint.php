<?php

namespace Conduit\Endpoint;

use Conduit\Client\LLMClient;

/**
 * Base for every endpoint (Chat, Image).
 *
 * An endpoint is created through the LLMClient, filled with fluent setters
 * ($oClient->chat()->model(...)->maxTokens(...)) and sent with call(). It
 * keeps the API key and a reference to the client, which knows the selected
 * provider and hands out the matching adapter.
 */
abstract class AbstractLLMEndpoint
{
    protected string    $sApiKey;
    protected LLMClient $oClient;
    protected string    $sModel;

    /**
     * @param string    $sApiKey API key of the provider account.
     * @param LLMClient $oClient Client with a provider already set.
     */
    public function __construct(string $sApiKey, LLMClient $oClient)
    {
        $this->sApiKey = $sApiKey;
        $this->oClient = $oClient;
    }

    /**
     * Sends the assembled request to the provider.
     *
     * @return object Response object of the concrete endpoint (ChatResponse or ImageResponse).
     */
    abstract public function call(): object;

    /**
     * Sets the model to use for this request.
     *
     * @param string $sModel Model id (e.g. 'gpt-5.6-luna', 'claude-opus-4-5').
     * @return static
     */
    public function model(string $sModel): static
    {
        $this->sModel = $sModel;
        return $this;
    }
}
