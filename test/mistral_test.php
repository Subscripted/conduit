<?php

require __DIR__ . '/../vendor/autoload.php';

use Conduit\Client\LLMClient;
use Conduit\Entity\Content;
use Conduit\Entity\Tool;

$sAPIKey = getenv('MISTRAL_API_KEY') ?: '';
//LLM client o:Object
$oLLMClient = new LLMClient($sAPIKey);

//Chat o:Object
$oChat = $oLLMClient->chat();

$oChat
    ->model('open-mistral-7b')
    ->maxTokens(2000)
    ->instruction('You are a helpfull assistant!')
    ->content([
        Content::text('Wie wird das Wetter heute?')
    ])
    ->tools([
        Tool::webSearch()
    ]);

$oResponse = $oChat->call();

if (!$oResponse->hasErrors()) {
    echo $oResponse->getText();
}
