<?php

namespace entity\dto;

use Output;
use traits\HasImageData;

/**
 * A single generated image.
 *
 * Built by ImageResponse from the normalized adapter array. The actual image
 * fields (data, format, url, status) come from the HasImageData trait, only
 * the size is added here.
 *
 * Depending on the provider either image_data (base64) or image_url is
 * filled — both at once is not guaranteed.
 */
class ImageOutput implements Output
{
    use HasImageData;

    private string $sSize = '';

    /** @return string Dimensions of the image (e.g. '1024x1024'), empty when not delivered. */
    public function getSize(): string { return $this->sSize; }

    /**
     * Builds an image from the normalized adapter array.
     *
     * @param array $aData Entry from the outputs array with image_data, image_format,
     *                     image_url, status and size.
     * @return self
     */
    public static function fromArray(array $aData): self
    {
        $oInstance        = new self();
        $oInstance->hydrateImageData($aData);
        $oInstance->sSize = $aData['size'] ?? '';
        return $oInstance;
    }

    /**
     * Returns the image as an array, empty fields are left out.
     *
     * @return array Image fields from HasImageData plus size.
     */
    public function __toArray(): array
    {
        $aResult = $this->toArrayImageData();
        if ($this->sSize !== '') {
            $aResult['size'] = $this->sSize;
        }
        return $aResult;
    }

    /**
     * @return string Base64 data of the image, empty when the provider only returned a URL.
     */
    public function __toString(): string
    {
        return $this->sImageData;
    }
}
