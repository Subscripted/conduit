<?php

namespace Conduit\Factory;

use Conduit\Adapter\AnthropicAdapter;
use Conduit\Adapter\OpenAIAdapter;
use Conduit\Contract\LLMAdapter;
use Conduit\Enum\AIProvider;
use Conduit\Exception\UnsupportedCapabilityException;

/**
 * Creates the adapter for the selected provider.
 *
 * Called by the endpoints in call(). The rest of the code only ever sees
 * the LLMAdapter interface. A new provider needs a case here plus a case in
 * the AIProvider enum.
 */
class AdapterFactory
{
    /**
     * Builds the adapter for the given provider.
     *
     * @param AIProvider $oProvider Selected provider.
     * @param string     $sApiKey   API key passed to the adapter.
     * @return LLMAdapter Adapter the endpoints send their requests through.
     * @throws UnsupportedCapabilityException If the provider has no adapter yet (e.g. Google).
     * @throws \UnhandledMatchError If the provider is not handled at all.
     */
    public static function make(AIProvider $oProvider, string $sApiKey): LLMAdapter
    {
        return match ($oProvider) {
            AIProvider::OpenAI    => new OpenAIAdapter($sApiKey),
            AIProvider::Anthropic => new AnthropicAdapter($sApiKey),
            AIProvider::Google    => throw new UnsupportedCapabilityException('Google adapter not yet implemented'),
        };
    }
}
