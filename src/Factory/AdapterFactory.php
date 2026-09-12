<?php

namespace Conduit\Factory;

use Conduit\Adapter\AnthropicAdapter;
use Conduit\Adapter\OpenAIAdapter;
use Conduit\Contract\LLMAdapter;
use Conduit\Enum\AIProvider;
use Conduit\Exception\ConfigurationException;
use Conduit\Exception\UnsupportedCapabilityException;

/**
 * Resolves the provider for a model id and builds its adapter.
 *
 * Called by the endpoints in call() with the model id and API key. This is
 * the only place that turns a model id into a provider (AIProvider::
 * fromModel()) and then into a concrete adapter — the client and endpoints
 * never pick a provider themselves. A new provider needs a case in
 * AIProvider (with a matching pattern) plus a case here.
 */
class AdapterFactory
{
    /**
     * @param string          $sModel             Model id set on the endpoint via ->model(...).
     * @param string          $sApiKey            API key passed to the adapter.
     * @param AIProvider|null $oProviderOverride  Provider set via ->provider(...), used as-is
     *                                            instead of AIProvider::fromModel() when given.
     *                                            For model ids the built-in patterns can't
     *                                            recognize (custom deployments, fine-tune aliases, ...).
     * @return LLMAdapter Adapter for the resolved provider.
     * @throws ConfigurationException If no override is given and the provider can't be determined from the model id.
     * @throws UnsupportedCapabilityException If the provider has no adapter yet (e.g. Google).
     */
    public static function make(string $sModel, string $sApiKey, ?AIProvider $oProviderOverride = null): LLMAdapter
    {
        return match ($oProviderOverride ?? AIProvider::fromModel($sModel)) {
            AIProvider::OpenAI    => new OpenAIAdapter($sApiKey),
            AIProvider::Anthropic => new AnthropicAdapter($sApiKey),
            AIProvider::Google    => throw new UnsupportedCapabilityException('Google adapter not yet implemented'),
        };
    }
}
