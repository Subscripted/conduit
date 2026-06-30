<?php

namespace entity\core;

class Context
{
    public static function user(array|string $content): array
    {
        return [
            'role' => 'user',
            'content' => $content
        ];
    }

    public static function assistant(array|string $content): array
    {
        return [
            'role' => 'assistant',
            'content' => $content
        ];
    }

    public static function tool(array $output): array
    {
        return [
            'role' => 'tool',
            'content' => $output
        ];
    }

    public static function from(array $messages): array
    {
        return array_map(function ($message) {

            if (is_string($message)) {
                return self::user($message);
            }

            if (is_array($message) && isset($message['role'], $message['content'])) {
                return $message;
            }

            throw new \InvalidArgumentException(
                'Invalid context message format'
            );

        }, $messages);
    }
}