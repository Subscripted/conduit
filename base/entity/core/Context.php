<?php

namespace entity\core;

class Context
{
    public static function user(array|string $mContent): array
    {
        return ['role' => 'user', 'content' => $mContent];
    }

    public static function assistant(array|string $mContent): array
    {
        return ['role' => 'assistant', 'content' => $mContent];
    }

    /**
     * Tool result to send back after a function_call.
     * $sToolCallId comes from ChatOutput::getCallId().
     */
    public static function tool(string $sToolCallId, string|array $mOutput): array
    {
        return [
            'role'         => 'tool_result',
            'tool_call_id' => $sToolCallId,
            'content'      => $mOutput,
        ];
    }

    public static function from(array $aMessages): array
    {
        $aResult = [];
        foreach ($aMessages as $mMessage) {
            if (is_string($mMessage)) {
                $aResult[] = self::user($mMessage);
                continue;
            }
            if (is_array($mMessage) && isset($mMessage['role'], $mMessage['content'])) {
                $aResult[] = $mMessage;
                continue;
            }
            throw new \InvalidArgumentException('Invalid context message format');
        }
        return $aResult;
    }
}
