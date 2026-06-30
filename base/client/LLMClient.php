<?php

use endpoint\Chat;
use type\AIProvider;

class LLMClient
{
    private AIProvider $AIProvider;
    private string $API_KEY;

    /**
     * @throws Exception
     */
    public function __construct(string $API_KEY)
    {
        if (empty($API_KEY)) {
            throw new Exception("API Key may not be empty");
        }

        $this->API_KEY = $API_KEY;
    }

    public function chat(): Chat
    {
        if (!isset($this->AIProvider)) {
            throw new RuntimeException('AIProvider not set! Use ...->setAIProvider(enum:AIProvider)');
        }
        return new Chat($this->API_KEY, $this);
    }


    public function image()
    {
        if (!isset($this->AIProvider)) {
            throw new RuntimeException('AIProvider not set! Use ...->setAIProvider(enum:AIProvider)');
        }
    }

    public function setAIProvider(AIProvider $AIProvider): void
    {
        $this->AIProvider = $AIProvider;
    }

    public function getAIProvider(): AIProvider
    {
        return $this->AIProvider;
    }


}