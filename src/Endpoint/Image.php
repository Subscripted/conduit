<?php

namespace Conduit\Endpoint;

use Conduit\Exception\ConduitException;
use Conduit\Factory\AdapterFactory;
use Conduit\Response\ImageResponse;

/**
 * Endpoint for image generation and image editing.
 *
 * Not created directly — obtained from the LLMClient and filled with fluent
 * setters: $oClient->image()->model(...)->prompt(...)->call(). Only call()
 * sends the request and returns an ImageResponse.
 *
 * Without images() a new image is created from the prompt alone, with
 * images() the passed images are edited as a template.
 *
 * Only supported by providers with an image API — the AnthropicAdapter
 * throws a RuntimeException that comes back as an error response.
 */
class Image extends AbstractLLMEndpoint
{
    private string $sPrompt       = '';
    private array  $aImages       = [];
    private string $sSize         = '';
    private string $sQuality      = '';
    private string $sOutputFormat = '';

    /**
     * Sends the assembled request to the provider.
     *
     * A provider error is deliberately not propagated but returned as an
     * ImageResponse with an error set — the caller checks hasErrors()
     * instead of catching.
     *
     * @return ImageResponse Generated image or error response.
     */
    public function call(): ImageResponse
    {
        $oAdapter = AdapterFactory::make(
            $this->oClient->getAIProvider(),
            $this->sApiKey
        );

        try {
            $aNormalized = $oAdapter->image([
                'model'        => $this->sModel,
                'prompt'       => $this->sPrompt,
                'images'       => $this->aImages,
                'size'         => $this->sSize,
                'quality'      => $this->sQuality,
                'outputFormat' => $this->sOutputFormat,
            ]);
            return ImageResponse::fromArray($aNormalized);
        } catch (ConduitException $oException) {
            return ImageResponse::fromError($oException);
        }
    }

    /**
     * Sets the description to generate or edit by.
     *
     * @param string $sPrompt Description of the wanted image.
     * @return self
     */
    public function prompt(string $sPrompt): self
    {
        $this->sPrompt = $sPrompt;
        return $this;
    }

    /**
     * Template images to edit. Without this call a new image is created from
     * the prompt alone.
     *
     * @param array $aImages List of image URLs (https://) to edit.
     * @return self
     */
    public function images(array $aImages): self
    {
        $this->aImages = $aImages;
        return $this;
    }

    /**
     * Sets the image size. Without it the provider uses its default.
     *
     * @param string $sSize Size as edge dimensions, e.g. '1024x1024'.
     * @return self
     */
    public function size(string $sSize): self
    {
        $this->sSize = $sSize;
        return $this;
    }

    /**
     * Sets the image quality. Without it the provider uses its default.
     *
     * @param string $sQuality Quality level, e.g. 'low', 'medium', 'high'.
     * @return self
     */
    public function quality(string $sQuality): self
    {
        $this->sQuality = $sQuality;
        return $this;
    }

    /**
     * Sets the output file format. Without it the provider uses its default.
     *
     * @param string $sOutputFormat Format, e.g. 'png', 'jpeg', 'webp'.
     * @return self
     */
    public function outputFormat(string $sOutputFormat): self
    {
        $this->sOutputFormat = $sOutputFormat;
        return $this;
    }
}
