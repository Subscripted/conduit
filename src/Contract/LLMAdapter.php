<?php

namespace Conduit\Contract;

use Conduit\Exception\UnsupportedCapabilityException;

/**
 * Contract every provider adapter must fulfil. Both methods take the
 * provider-neutral payload of the endpoint and return the response in the
 * normalized array format. Only this makes the providers interchangeable
 * without the calling code changing.
 *
 * If a provider cannot serve an endpoint (Anthropic has no images), the
 * method throws a Conduit\Exception\UnsupportedCapabilityException instead of
 * faking an empty result.
 */
interface LLMAdapter
{
    /**
     * Sends a chat request to the provider.
     *
     * @param array $aPayload Neutral chat payload (model, instruction, context,
     *                        content, tools, effort, jsonSchema, ...).
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors, warnings.
     */
    public function chat(array $aPayload): array;

    /**
     * Sends an image request to the provider.
     *
     * @param array $aPayload Neutral image payload (model, prompt, images, width, height, ...).
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors, warnings.
     * @throws UnsupportedCapabilityException If the provider has no image endpoint.
     */
    public function image(array $aPayload): array;

    /**
     * Sends a chat request to the provider and streams the answer.
     *
     * Same neutral payload as chat(); yields normalized stream-event arrays
     * (the shape Conduit\Response\ChatStreamEvent::fromArray() consumes)
     * instead of returning one final array. Unlike chat(), a transport or API
     * failure is not caught here — it propagates from the generator so the
     * caller (Chat::stream()) can turn it into a trailing error event.
     *
     * @param array $aPayload Neutral chat payload, same shape as chat().
     * @return \Generator<array> Normalized stream events.
     */
    public function streamChat(array $aPayload): \Generator;
}
