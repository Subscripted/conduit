<?php

interface LLMAdapter
{

    public function call(array $payload) : array;

}