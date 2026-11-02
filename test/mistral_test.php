<?php

require __DIR__ . '/../vendor/autoload.php';

use Conduit\Client\LLMClient;
use Conduit\Entity\Content;
use Conduit\Entity\Tool;
use Conduit\Response\ChatStreamEvent;

$sAPIKey = 'your-token';
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
    ]);

// stream() returns a Generator of ChatStreamEvent, not a ChatResponse —
// call() is the one with hasErrors()/getText().
foreach ($oChat->stream() as $oEvent) {
    
    echo 'Index: ' . $oEvent->getIndex() . '<br>';
    /** @var ChatStreamEvent $oEvent */
    if ($oEvent->isTextDelta()) {
        echo $oEvent->getDelta();
    } elseif ($oEvent->isOutputDone() && $oEvent->getOutput() !== null) {
        echo "\n[", $oEvent->getOutput()->getType()->value, "] ", $oEvent->getOutput(), "\n";
    } elseif ($oEvent->isResponseDone()) {
        echo "\n\n--- done: model={$oEvent->getModel()} input_tokens={$oEvent->getInputTokens()} output_tokens={$oEvent->getOutputTokens()} ---\n";
        if ($oEvent->getWarnings() !== []) {
            echo 'warnings: ', implode('; ', $oEvent->getWarnings()), "\n";
        }
    } elseif ($oEvent->isError()) {
        echo "\nError: ", $oEvent->getError()?->getMessage(), "\n";
        exit(1);
    }
}

