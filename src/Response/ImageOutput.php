<?php

namespace Conduit\Response;

use Conduit\Contract\Output;
use Conduit\Support\HasImageData;

/**
 * A single generated image.
 *
 * Built by ImageResponse from the normalized adapter array. The actual image
 * fields (data, format, url, status) come from the HasImageData trait, only
 * the width and height are added here.
 *
 * Depending on the provider either image_data (base64) or image_url is
 * filled — both at once is not guaranteed.
 */
class ImageOutput implements Output
{
    use HasImageData;

    private int $iWidth  = 0;
    private int $iHeight = 0;

    /** @return int Width in pixels, 0 when not delivered. */
    public function getWidth(): int { return $this->iWidth; }

    /** @return int Height in pixels, 0 when not delivered. */
    public function getHeight(): int { return $this->iHeight; }

    /**
     * Builds an image from the normalized adapter array.
     *
     * @param array $aData Entry from the outputs array with image_data, image_format,
     *                     image_url, status, width and height.
     * @return self
     */
    public static function fromArray(array $aData): self
    {
        $oInstance = new self();
        $oInstance->hydrateImageData($aData);
        $oInstance->iWidth  = (int) ($aData['width'] ?? 0);
        $oInstance->iHeight = (int) ($aData['height'] ?? 0);
        return $oInstance;
    }

    /**
     * Returns the image as an array, empty fields are left out.
     *
     * @return array Image fields from HasImageData plus width and height.
     */
    public function __toArray(): array
    {
        $aResult = $this->toArrayImageData();
        if ($this->iWidth > 0)  $aResult['width']  = $this->iWidth;
        if ($this->iHeight > 0) $aResult['height'] = $this->iHeight;
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
