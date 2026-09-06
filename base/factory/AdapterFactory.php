<?php

namespace factory;

use adapter\AnthropicAdapter;
use adapter\OpenAIAdapter;
use LLMAdapter;
use type\AIProvider;

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
     * @throws \RuntimeException If the provider has no adapter yet (e.g. Google).
     * @throws \UnhandledMatchError If the provider is not handled at all.
     */
    public static function make(AIProvider $oProvider, string $sApiKey): LLMAdapter
    {
        return match ($oProvider) {
            AIProvider::OpenAI    => new OpenAIAdapter($sApiKey),
            AIProvider::Anthropic => new AnthropicAdapter($sApiKey),
            AIProvider::Google    => throw new \RuntimeException('Google adapter not yet implemented'),
        };
    }
}
