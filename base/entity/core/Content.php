<?php

namespace entity\core;

class Content
{
    public static function text(string $sText): array
    {
        return ['type' => 'text', 'text' => $sText];
    }

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

    public static function images(string|array $mUrlsOrBase64): array
    {
        $aItems  = is_array($mUrlsOrBase64) ? $mUrlsOrBase64 : [$mUrlsOrBase64];
        $aResult = [];
        foreach ($aItems as $sItem) {
            $aResult[] = self::image($sItem);
        }
        return $aResult;
    }

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
