<?php

namespace Conduit\Contract;

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
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors.
     */
    public function chat(array $aPayload): array;

    /**
     * Sends an image request to the provider.
     *
     * @param array $aPayload Neutral image payload (model, prompt, images, size, ...).
     * @return array Normalized response: model, input_tokens, output_tokens, outputs, errors.
     * @throws \Conduit\Exception\UnsupportedCapabilityException If the provider has no image endpoint.
     */
    public function image(array $aPayload): array;
}
