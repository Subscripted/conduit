<?php

namespace endpoint;

use AbstractLLMEndpoint;
use entity\ChatResponse;
use type\AIProvider;

class Chat extends AbstractLLMEndpoint
{
    private array $context = [];
    private array $content = [];
    private string $instruction;
    private string $user;

    public function __construct(string $API_KEY, \LLMClient $client)
    {
        parent::__construct($API_KEY, $client);
    }


    function call(): ChatResponse
    {
        $response = new ChatResponse();
        switch ($this->client->getAIProvider()) {
            case AIProvider::OpenAI:
                break;

            case AIProvider::Antrophic:
                break;


            case AIProvider::Google:
                break;
        }

        return $response;
    }

    public function model(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function context(array $context = []): self
    {
        $this->context = $context;
        return $this;
    }

    public function content(array $content = []): self
    {
        $this->content = $content;
        return $this;
    }


    public function instruction(string $instruction): self
    {
        $this->instruction = $instruction;
        return $this;
    }


    public function user(string $user): self
    {
        $this->user = $user;
        return $this;
    }
}