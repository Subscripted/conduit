<?php

namespace Conduit\Entity;

/**
 * Building blocks for the user input of a request.
 *
 * A stateless factory: the static methods return the blocks passed to
 * Chat::content() or to Context::user()/assistant(). The blocks are
 * provider-neutral — the adapter translates them into its own format.
 *
 * For a data: URI the media type and base64 data are passed separately, for
 * a normal URL the provider fetches the file itself.
 */
class Content
{
    /**
     * @param string $sText Text of the user input.
     * @return array Content block of type text.
     */
    public static function text(string $sText): array
    {
        return ['type' => 'text', 'text' => $sText];
    }

    /**
     * @param string $sUrlOrBase64 Image URL or data: URI (data:image/jpeg;base64,...).
     * @return array Content block of type image.
     */
    public static function image(string $sUrlOrBase64): array
    {
        if (str_starts_with($sUrlOrBase64, 'data:')) {
            preg_match('/^data:([^;]+);base64,(.+)$/s', $sUrlOrBase64, $aMatches);
            return [
                'type'       => 'image',
                'data'       => $aMatches[2] ?? '',
                'media_type' => $aMatches[1] ?? 'image/jpeg',
            ];
        }
        return ['type' => 'image', 'url' => $sUrlOrBase64];
    }

    /**
     * @param string      $sUrlOrBase64 File URL or data: URI (data:application/pdf;base64,...).
     * @param string|null $sFilename    Optional filename the model should see.
     * @return array Content block of type file.
     */
    public static function file(string $sUrlOrBase64, ?string $sFilename = null): array
    {
        if (str_starts_with($sUrlOrBase64, 'data:')) {
            preg_match('/^data:([^;]+);base64,(.+)$/s', $sUrlOrBase64, $aMatches);
            $aResult = [
                'type'       => 'file',
                'data'       => $aMatches[2] ?? '',
                'media_type' => $aMatches[1] ?? 'application/octet-stream',
            ];
            if ($sFilename !== null) {
                $aResult['filename'] = $sFilename;
            }
            return $aResult;
        }

        $aResult = ['type' => 'file', 'url' => $sUrlOrBase64];
        if ($sFilename !== null) {
            $aResult['filename'] = $sFilename;
        }
        return $aResult;
    }

    /**
     * Several images at once.
     *
     * @param string|array $mUrlsOrBase64 One URL/data: URI or a list of them.
     * @return array List of content blocks of type image.
     */
    public static function images(string|array $mUrlsOrBase64): array
    {
        $aItems  = is_array($mUrlsOrBase64) ? $mUrlsOrBase64 : [$mUrlsOrBase64];
        $aResult = [];
        foreach ($aItems as $sItem) {
            $aResult[] = self::image($sItem);
        }
        return $aResult;
    }

    /**
     * Several files at once.
     *
     * @param string|array $mUrlsOrBase64 One URL/data: URI or a list of them.
     * @param string|null  $sFilename     Optional filename set on every block.
     * @return array List of content blocks of type file.
     */
    public static function files(string|array $mUrlsOrBase64, ?string $sFilename = null): array
    {
        $aItems  = is_array($mUrlsOrBase64) ? $mUrlsOrBase64 : [$mUrlsOrBase64];
        $aResult = [];
        foreach ($aItems as $sItem) {
            $aResult[] = self::file($sItem, $sFilename);
        }
        return $aResult;
    }
}
