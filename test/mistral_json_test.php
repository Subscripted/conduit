<?php

require __DIR__ . '/../vendor/autoload.php';

use Conduit\Client\LLMClient;
use Conduit\Entity\Content;

$sAPIKey = getenv('MISTRAL_API_KEY');

$oLLMClient = new LLMClient($sAPIKey);

$oChat = $oLLMClient->chat();

$oChat
    ->model('mistral-small-latest')
    ->maxTokens(2000)
    ->instruction('You extract structured data from text. Answer only with the requested JSON.')
    ->content([
        Content::text('Lorenz is 19 years old and lives in Germany.'),
    ])
    ->jsonSchema([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer'],
            'country_code' => ['type' => 'string'],
        ],
        'required' => ['name', 'age', 'country_code'],
        'additionalProperties' => false,
    ]);

$oResponse = $oChat->call();

if ($oResponse->hasErrors()) {
    echo 'Error: ', $oResponse->getFirstError()->getMessage(), "\n";
    exit(1);
}

echo $oResponse->getText(), "\n";

$aData = json_decode($oResponse->getText(), true);
var_dump($aData);
