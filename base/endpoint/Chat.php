<?php

namespace endpoint;

use AbstractLLMEndpoint;
use entity\ChatResponse;
use factory\AdapterFactory;

class Chat extends AbstractLLMEndpoint
{
    private array $context = [];
    private array $content = [];
    private string $instruction;
    private string $user;

    public function __construct(private readonly string $apiKey, \LLMClient $client)
    {
        parent::__construct($apiKey, $client);
    }


    /**
     * @throws \Exception
     */
    function call(): ChatResponse
    {
        $adapter = AdapterFactory::make(
            $this->client->getAIProvider(),
            $this->apiKey
        );

        try {
            $normalized = $adapter->call([
                'model' => $this->model,
                'instruction' => $this->instruction ?? null,
                'user' => $this->user ?? 'user',
                'context' => $this->context,
                'content' => $this->content,
            ]);
            return ChatResponse::fromArray($normalized);
        } catch (\RuntimeException $e) {
            return ChatResponse::error($e->getMessage());
        }
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