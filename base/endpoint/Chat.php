<?php

namespace endpoint;

use AbstractLLMEndpoint;
use client\LLMClient;
use entity\dto\ChatResponse;
use factory\AdapterFactory;
use traits\HasTools;

class Chat extends AbstractLLMEndpoint
{
    use HasTools;

    private array  $aContext     = [];
    private array  $aContent     = [];
    private string $sInstruction = '';
    private string $sUser        = 'user';
    private int    $iMaxTokens   = 1024;

    public function __construct(string $sApiKey, LLMClient $oClient)
    {
        parent::__construct($sApiKey, $oClient);
    }

    public function call(): ChatResponse
    {
        $oAdapter = AdapterFactory::make(
            $this->oClient->getAIProvider(),
            $this->sApiKey
        );

        try {
            $aNormalized = $oAdapter->chat([
                'model'       => $this->sModel,
                'instruction' => $this->sInstruction ?: null,
                'maxTokens'   => $this->iMaxTokens,
                'user'        => $this->sUser,
                'context'     => $this->aContext,
                'content'     => $this->aContent,
                'tools'       => $this->getTools(),
            ]);
            return ChatResponse::fromArray($aNormalized);
        } catch (\RuntimeException $oException) {
            return ChatResponse::error($oException->getMessage());
        }
    }

    public function model(string $sModel): self
    {
        $this->sModel = $sModel;
        return $this;
    }

    public function context(array $aContext): self
    {
        $this->aContext = $aContext;
        return $this;
    }

    public function content(array $aContent): self
    {
        $this->aContent = $aContent;
        return $this;
    }

    public function instruction(string $sInstruction): self
    {
        $this->sInstruction = $sInstruction;
        return $this;
    }

    public function user(string $sUser): self
    {
        $this->sUser = $sUser;
        return $this;
    }

    public function maxTokens(int $iMaxTokens): self
    {
        $this->iMaxTokens = $iMaxTokens;
        return $this;
    }
}
