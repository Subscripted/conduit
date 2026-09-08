<?php

namespace Conduit\Enum;

/**
 * Provider-neutral kinds of tool a request can offer the model.
 *
 * The Tool factory stamps the matching value into a definition's `_type`
 * key; each adapter switches on it to emit the provider's own tool format.
 * Backed by the wire string so `ToolType::Mcp->value` and a raw `'mcp'`
 * stay interchangeable during hydration.
 */
enum ToolType: string
{
    /** Provider-run web search (Tool::webSearch()). */
    case WebSearch = 'web_search';

    /** Provider-run fetch of a named page (Tool::webFetch()). */
    case WebFetch = 'web_fetch';

    /** Custom function the model asks the caller to run (Tool::function()). */
    case Function = 'function';

    /** Image generation inside a chat turn (Tool::imageGeneration()). */
    case ImageGeneration = 'image_generation';

    /** Remote MCP server whose tools the model may call (Tool::mcp()). */
    case Mcp = 'mcp';
}
