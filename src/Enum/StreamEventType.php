<?php

namespace Conduit\Enum;

/**
 * Kinds of events a streamed chat request can yield.
 *
 * Deliberately separate from OutputType: OutputType describes the kind of a
 * finished block (Text, FunctionCall, ...), StreamEventType describes what
 * just happened while that block was being built. ChatStreamEvent sets this
 * while streaming; OutputDone carries the finished block as a normal
 * ChatOutput built from the same normalized shape call() already uses.
 */
enum StreamEventType: string
{
    case OutputStart               = 'output_start';
    case TextDelta                 = 'text_delta';
    case ThinkingDelta             = 'thinking_delta';
    case RefusalDelta              = 'refusal_delta';
    case FunctionCallArgumentsDelta = 'function_call_arguments_delta';
    case OutputDone                = 'output_done';
    case ResponseDone              = 'response_done';
    case Error                     = 'error';
}
