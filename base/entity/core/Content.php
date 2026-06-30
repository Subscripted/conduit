<?php

namespace entity\core;

class Content
{

    public static function text(string $text): array
    {
        return ['type' => 'input_text', 'text' => $text];
    }

    public static function image(string $urlOrBase64): array
    {
        return ['type' => 'input_image', 'image_url' => $urlOrBase64];
    }

    public static function file(string $urlOrBase64, ?string $filename = null): array
    {
        return array_filter([
            'type' => 'input_file',
            'file_data' => str_starts_with($urlOrBase64, 'data:') ? $urlOrBase64 : null,
            'file_url' => !str_starts_with($urlOrBase64, 'data:') ? $urlOrBase64 : null,
            'filename' => $filename,
        ]);
    }

    public static function images(string|array $urlsOrBase64): array
    {
        $items = is_array($urlsOrBase64) ? $urlsOrBase64 : [$urlsOrBase64];
        return array_map(fn($item) => self::image($item), $items);
    }

    public static function files(string|array $urlsOrBase64, ?string $filename = null): array
    {
        $items = is_array($urlsOrBase64) ? $urlsOrBase64 : [$urlsOrBase64];
        return array_map(fn($item) => self::file($item, $filename), $items);
    }
}