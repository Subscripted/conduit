<?php

namespace factory;

use adapter\OpenAIAdapter;
use LLMAdapter;
use type\AIProvider;

class AdapterFactory
{

    public static function make(AIProvider $AIProvider, string $apiKey): LLMAdapter
    {
        return match ($AIProvider) {
            AIProvider::OpenAI => new OpenAIAdapter($apiKey),
            AIProvider::Antrophic => throw new \Exception('To be implemented'),
            AIProvider::Google => throw new \Exception('To be implemented'),
        };
    }

}