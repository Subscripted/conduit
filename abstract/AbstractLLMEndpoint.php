<?php

use client\LLMClient;

abstract class AbstractLLMEndpoint
{
    protected string    $sApiKey;
    protected LLMClient $oClient;
    protected string    $sModel;

    public function __construct(string $sApiKey, LLMClient $oClient)
    {
        $this->sApiKey = $sApiKey;
        $this->oClient = $oClient;
    }

    abstract public function call(): object;
}
