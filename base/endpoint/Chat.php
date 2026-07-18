<?php

namespace endpoint;

use AbstractLLMEndpoint;
use entity\ChatResponse;
use factory\AdapterFactory;
use traits\HasTools;
use client\LLMClient;

class Chat extends AbstractLLMEndpoint
{

    use HasTools;

    private array $context = [];
    private array $content = [];
    private string $instruction;
    private string $user;
    private int $iMaxTokens = 100;

    public function __construct(private readonly string $apiKey, LLMClient $client)
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
            $normalized = $adapter->call('responses', [
                'model' => $this->model,
                'instruction' => $this->instruction ?? null,
                'maxTokens' => $this->iMaxTokens,
                'user' => $this->user ?? 'user',
                'context' => $this->context,
                'content' => $this->content,
                'tools' => $this->getTools(),
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

    public function maxTokens(int $maxTokens): self
    {
        $this->iMaxTokens = $maxTokens;
        return $this;
    }
}