<?php

namespace type;

/**
 * Kinds of output blocks in a response.
 *
 * The adapters set this type while normalizing; ChatOutput and ImageOutput
 * map it. Which fields of a block are filled depends solely on the type —
 * so check isText(), isFunctionCall() etc. before every getter.
 */
enum OutputType: string
{
    case Text         = 'text';
    case FunctionCall = 'function_call';
    case WebSearch    = 'web_search';
    case WebFetch     = 'web_fetch';
    case Image        = 'image';
    case Refusal      = 'refusal';
    case Thinking     = 'thinking';
}
