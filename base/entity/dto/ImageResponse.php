<?php

namespace entity\dto;

use AbstractAIResponse;

/**
 * Response of an image request.
 *
 * Not created directly — built by Image::call() from the normalized adapter
 * array. Besides model, token usage and errors (from AbstractAIResponse) it
 * holds the generated images as ImageOutput objects.
 *
 * The shortcuts return an empty string when no image was produced — check
 * hasErrors() first.
 */
class ImageResponse extends AbstractAIResponse
{
    // ── Convenience accessors ─────────────────────────────────────────────

    /**
     * All generated images in the provider's order.
     *
     * @return ImageOutput[]
     */
    public function getOutputs(): array { return parent::getOutputs(); }

    /**
     * @return ImageOutput|null First generated image, or null when none was delivered.
     */
    public function getImage(): ?ImageOutput
    {
        $oOutput = $this->aOutputs[0] ?? null;
        return $oOutput instanceof ImageOutput ? $oOutput : null;
    }

    /**
     * @return ImageOutput[]
     */
    public function getImages(): array { return $this->aOutputs; }

    /** @return string Base64 data of the first image, empty when there is none. */
    public function getImageData(): string   { return $this->getImage()?->getImageData() ?? ''; }

    /** @return string File format of the first image (png, jpeg, webp), empty when there is none. */
    public function getImageFormat(): string { return $this->getImage()?->getImageFormat() ?? ''; }

    /** @return string URL of the first image, empty when the provider only returned base64 data. */
    public function getImageUrl(): string    { return $this->getImage()?->getImageUrl() ?? ''; }

    /** @return string Dimensions of the first image, empty when there is none. */
    public function getSize(): string        { return $this->getImage()?->getSize() ?? ''; }

    // ── Hydration ─────────────────────────────────────────────────────────

    /**
     * Builds the response from the normalized adapter array.
     *
     * @param array $aData Normalized response: model, input_tokens, output_tokens, outputs, errors.
     * @return self
     */
    public static function fromArray(array $aData): self
    {
        $oInstance = new self();
        $oInstance->hydrateFromArray($aData);
        return $oInstance;
    }

    /**
     * Maps a single output to an ImageOutput.
     *
     * @param array $aOutputData One entry from the adapter's outputs array.
     * @return ImageOutput
     */
    protected function hydrateOutput(array $aOutputData): ImageOutput
    {
        return ImageOutput::fromArray($aOutputData);
    }

    // ── Casting ───────────────────────────────────────────────────────────

    /**
     * @return string Base64 data of the first image.
     */
    public function __toString(): string
    {
        return $this->getImageData();
    }
}
