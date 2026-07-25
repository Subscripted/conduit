<?php

namespace factory;

use adapter\AnthropicAdapter;
use adapter\OpenAIAdapter;
use LLMAdapter;
use type\AIProvider;

class AdapterFactory
{
    public static function make(AIProvider $oProvider, string $sApiKey): LLMAdapter
    {
        return match ($oProvider) {
            AIProvider::OpenAI    => new OpenAIAdapter($sApiKey),
            AIProvider::Anthropic => new AnthropicAdapter($sApiKey),
            AIProvider::Google    => throw new \RuntimeException('Google adapter not yet implemented'),
        };
    }
}
