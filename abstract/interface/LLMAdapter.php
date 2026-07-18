<?php

interface LLMAdapter
{

    public function call(string $endpoint,array $payload) : array;

}