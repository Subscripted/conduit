<?php

namespace client;

use endpoint\Chat;
use RuntimeException;
use type\AIProvider;

class LLMClient
{
    private AIProvider $oProvider;

    public function __construct(private readonly string $sApiKey)
    {
        if (empty($sApiKey)) {
            throw new \InvalidArgumentException('API key may not be empty');
        }
    }

    public function chat(): Chat
    {
        $this->assertProviderSet();
        return new Chat($this->sApiKey, $this);
    }

    public function setAIProvider(AIProvider $oProvider): self
    {
        $this->oProvider = $oProvider;
        return $this;
    }

    public function getAIProvider(): AIProvider
    {
        $this->assertProviderSet();
        return $this->oProvider;
    }

    private function assertProviderSet(): void
    {
        if (!isset($this->oProvider)) {
            throw new RuntimeException('No AIProvider set. Call setAIProvider(AIProvider::...) first.');
        }
    }
}
