<?php

interface LLMAdapter
{
    public function chat(array $aPayload): array;
}
