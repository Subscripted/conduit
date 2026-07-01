<?php

use entity\core\Content;
use type\AIProvider;
use client\LLMClient;
use entity\core\Tool;

$files = [];


$apikey = 'randomasskey';
$client = new LLMClient($apikey);

$client->setAIProvider(AIProvider::Antrophic);
$chatResponse = $client->chat()
    ->user('user')
    ->model('opus-4.6')
    ->instruction('Doah')
    ->context(
        [
        ]
    )
    ->content(
        [
            Content::text('Moin, ich habe her 3 Files die du anschauen sollst:'),
            Content::files([$files]),
            Content::text('Dazu habe ich noch 1 Bild was du dir anschauen sollst:'),
            Content::image('https://machmal.de/boah.png'),
            Content::text('Check da mal was rum.')
        ]
    )->tools(
        [
            Tool::webSearch(),
        ]
    )
    ->call();
if (empty($chatResponse->getErrors())) {
    $success = true;
}


