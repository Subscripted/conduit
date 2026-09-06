<?php

namespace traits;

/**
 * Image fields shared by output objects that are not otherwise related:
 * ChatOutput (via the image_generation tool) and ImageOutput. They are
 * different things and do not inherit from each other, so the shared part
 * lives in a trait instead of a common base class.
 *
 * Depending on the provider either image_data or image_url is filled.
 */
trait HasImageData
{
    protected string $sImageData   = '';
    protected string $sImageFormat = '';
    protected string $sImageUrl    = '';
    protected string $sStatus      = '';

    /** @return string Image as base64, empty when the provider only returned a URL. */
    public function getImageData(): string   { return $this->sImageData; }

    /** @return string File format of the image (png, jpeg, webp). */
    public function getImageFormat(): string { return $this->sImageFormat; }

    /** @return string URL of the image, empty when the provider only returned base64 data. */
    public function getImageUrl(): string    { return $this->sImageUrl; }

    /** @return string Status of the image at the provider (e.g. for partial images). */
    public function getStatus(): string      { return $this->sStatus; }

    /**
     * Takes the image fields from the normalized adapter array.
     *
     * @param array $aData Normalized response with image_data, image_format, image_url, status.
     */
    protected function hydrateImageData(array $aData): void
    {
        $this->sImageData   = $aData['image_data'] ?? '';
        $this->sImageFormat = $aData['image_format'] ?? '';
        $this->sImageUrl    = $aData['image_url'] ?? '';
        $this->sStatus      = $aData['status'] ?? '';
    }

    /**
     * Returns the set image fields as an array (empty fields are left out).
     *
     * @return array Partial payload for __toArray().
     */
    protected function toArrayImageData(): array
    {
        $aResult = [];
        if ($this->sImageData !== '') {
            $aResult['image_data']   = $this->sImageData;
            $aResult['image_format'] = $this->sImageFormat;
        }
        if ($this->sImageUrl !== '') {
            $aResult['image_url'] = $this->sImageUrl;
        }
        if ($this->sStatus !== '') {
            $aResult['status'] = $this->sStatus;
        }
        return $aResult;
    }
}
