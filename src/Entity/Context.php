<?php

namespace Conduit\Entity;

/**
 * Building blocks for the conversation history so far.
 *
 * A stateless factory: the static methods return the messages passed to
 * Chat::context(). This lets the model know what was asked and answered
 * before — the history must be sent in full with every request, the API
 * remembers nothing.
 */
class Context
{
    /**
     * @param array|string $mContent Content blocks from Content::... or plain text.
     * @return array Message with role 'user'.
     */
    public static function user(array|string $mContent): array
    {
        return ['role' => 'user', 'content' => $mContent];
    }

    /**
     * @param array|string $mContent Content blocks from Content::... or plain text.
     * @return array Message with role 'assistant'.
     */
    public static function assistant(array|string $mContent): array
    {
        return ['role' => 'assistant', 'content' => $mContent];
    }

    /**
     * Result of a function call, sent back after a function_call output.
     *
     * @param string       $sToolCallId Call id from ChatOutput::getCallId().
     * @param string|array $mOutput     Return value of the called function.
     * @return array Message with role 'tool_result'.
     */
    public static function tool(string $sToolCallId, string|array $mOutput): array
    {
        return [
            'role'         => 'tool_result',
            'tool_call_id' => $sToolCallId,
            'content'      => $mOutput,
        ];
    }

    /**
     * Answer to an MCP tool call the model wants confirmed (require_approval
     * 'always'). Sent back after an McpApprovalRequest output block. OpenAI
     * only — the Anthropic MCP connector has no approval step and drops it.
     *
     * @param string $sApprovalRequestId Id from ChatOutput::getCallId() of the approval request.
     * @param bool   $bApprove           True to let the call run, false to decline it.
     * @return array Message with role 'mcp_approval_response'.
     */
    public static function mcpApproval(string $sApprovalRequestId, bool $bApprove): array
    {
        return [
            'role'                => 'mcp_approval_response',
            'approval_request_id' => $sApprovalRequestId,
            'approve'             => $bApprove,
        ];
    }

    /**
     * Turns a mixed list into valid messages: plain strings become user()
     * messages, ready-made messages are passed through unchanged.
     *
     * @param array $aMessages List of strings and/or messages with role and content.
     * @return array List of valid messages for Chat::context().
     * @throws \InvalidArgumentException If an entry is neither a string nor a valid message.
     */
    public static function from(array $aMessages): array
    {
        $aResult = [];
        foreach ($aMessages as $mMessage) {
            if (is_string($mMessage)) {
                $aResult[] = self::user($mMessage);
                continue;
            }
            // Normal messages carry role + content; control messages such as
            // mcpApproval() carry a role but no content — accept both.
            if (is_array($mMessage) && isset($mMessage['role'])
                && (isset($mMessage['content']) || str_starts_with($mMessage['role'], 'mcp_'))
            ) {
                $aResult[] = $mMessage;
                continue;
            }
            throw new \InvalidArgumentException('Invalid context message format');
        }
        return $aResult;
    }
}
