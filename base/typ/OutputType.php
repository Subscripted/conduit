<?php

namespace type;

enum OutputType: string
{
    case Text         = 'text';
    case FunctionCall = 'function_call';
    case WebSearch    = 'web_search';
    case Image        = 'image';
    case Refusal      = 'refusal';
    case Thinking     = 'thinking';
}
