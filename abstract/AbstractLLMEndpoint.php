<?php

abstract class AbstractLLMEndpoint
{

    protected string $API_KEY;
    protected LLMClient $client;
    protected string $model;


    public function __construct(string $API_KEY, LLMClient $client){
        $this->API_KEY = $API_KEY;
    }

    abstract function call() : object;
}