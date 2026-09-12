<?php

namespace Conduit\Endpoint;

use Conduit\Enum\AIProvider;

/**
 * Base for every endpoint (Chat, Image).
 *
 * An endpoint is created through the LLMClient, filled with fluent setters
 * ($oClient->chat()->model(...)->maxTokens(...)) and sent with call(). It
 * keeps the API key; which provider it talks to is resolved by
 * AdapterFactory::make() from the model set via model(...), unless
 * provider(...) forces one.
 */
abstract class AbstractLLMEndpoint
{
    protected string $sApiKey;
    protected string $sModel;
    protected ?AIProvider $oProviderOverride = null;

    /**
     * @param string $sApiKey API key of the provider account.
     */
    public function __construct(string $sApiKey)
    {
        $this->sApiKey = $sApiKey;
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

    /**
     * Forces the provider for this request, bypassing AIProvider::fromModel().
     * Only needed for model ids the built-in patterns can't recognize —
     * custom deployments, fine-tune aliases, and similar edge cases.
     *
     * @param AIProvider $oProvider Provider to use regardless of the model id.
     * @return static
     */
    public function provider(AIProvider $oProvider): static
    {
        $this->oProviderOverride = $oProvider;
        return $this;
    }
}
