<?php

namespace type;

/**
 * Selectable model providers.
 *
 * Passed to LLMClient::setAIProvider() and decides which adapter the
 * AdapterFactory builds. A new provider needs a case here plus the matching
 * branch in the AdapterFactory.
 */
enum AIProvider
{
    case OpenAI;
    case Anthropic;
    case Google;
}
