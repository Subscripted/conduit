<?php

namespace Conduit\Enum;

/**
 * Reasoning / thinking depth of the model.
 *
 * Set via Chat::effort() and controls how long the model reasons before
 * answering. Higher levels give better results on hard tasks but cost more
 * output tokens and time.
 */
enum ThinkingEffort: string
{
    case Low    = 'low';
    case Medium = 'medium';
    case High   = 'high';
    case XHigh  = 'xhigh';
    case Max    = 'max';
}
