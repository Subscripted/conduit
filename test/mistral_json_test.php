<?php

require __DIR__ . '/../vendor/autoload.php';

use Conduit\Client\LLMClient;
use Conduit\Configuration\ConduitConfig;
use Conduit\Entity\Content;
use Conduit\Entity\JsonSchema;
use Conduit\Enum\JsonSchemaType;
use Conduit\Entity\Tool;

$sAPIKey = '';

$oLLMClient = new LLMClient($sAPIKey);
$oChat = $oLLMClient->chat();

$oChat
    ->model('mistral-small-latest')
    ->maxTokens(2000)
    ->instruction('You extract structured data from text.')
    ->content([
        Content::text('Generate a Long- and Short description about this article and give the price:  https://www.adidas.de/adizero-adios-pro-evo-3-schuh/KH7678.html?pr=taxonomy_rr&slot=1&rec=mt.'),
    ])
    ->tools([Tool::webSearch(['adidas.de'])])
    ->jsonSchema(
        JsonSchema::object()
            ->property('description_long', JsonSchemaType::String)
            ->property('description_short', JsonSchemaType::String)
            ->property('price', JsonSchemaType::Number)
    );

$oResponse = $oChat->call();

if ($oResponse->hasErrors()) {
    echo 'Error: ', $oResponse->getFirstError()->getMessage(), "\n";
    exit(1);
}

$sJson = $oResponse->getJson();
$aWebs = $oResponse->getWebSearches();
var_dump($sJson);
var_dump($aWebs);
