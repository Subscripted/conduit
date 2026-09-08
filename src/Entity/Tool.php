<?php

namespace Conduit\Entity;

use Conduit\Enum\ToolType;

/**
 * Tools available to the model for a request.
 *
 * A stateless factory: the static methods return the tool definitions
 * passed to Chat::tools(). The key _type holds a ToolType value and stays
 * provider-neutral, the adapters translate it into their own format.
 *
 * Web search and web fetch cost extra on top of the tokens — allowed_domains
 * and max_uses limit both the cost and the sources the model may use.
 */
class Tool
{
    /**
     * Web search performed by the provider.
     *
     * @param array      $aAllowedDomains Only these domains may be searched.
     * @param array      $aBlockedDomains These domains are excluded.
     * @param int|null   $iMaxUses        Optional max number of searches per request.
     * @param array|null $aUserLocation   Optional location from Tool::location().
     * @param string     $sContextSize    Amount of search result context (low, medium, high).
     * @return array Tool definition of type web_search.
     */
    public static function webSearch(
        array  $aAllowedDomains = [],
        array  $aBlockedDomains = [],
        ?int   $iMaxUses = null,
        ?array $aUserLocation = null,
        string $sContextSize = 'medium',
    ): array {
        $aResult = ['_type' => ToolType::WebSearch->value, 'context_size' => $sContextSize];
        if (!empty($aAllowedDomains)) $aResult['allowed_domains'] = $aAllowedDomains;
        if (!empty($aBlockedDomains)) $aResult['blocked_domains'] = $aBlockedDomains;
        if ($iMaxUses !== null)       $aResult['max_uses']        = $iMaxUses;
        if ($aUserLocation !== null)  $aResult['user_location']   = $aUserLocation;
        return $aResult;
    }

    /**
     * Fetch of a concrete page by the provider.
     *
     * @param array     $aAllowedDomains   Only these domains may be fetched.
     * @param array     $aBlockedDomains   These domains are excluded.
     * @param int|null  $iMaxUses          Optional max number of fetches per request.
     * @param bool|null $bCitations        Optional: include source citations in the answer text.
     * @param int|null  $iMaxContentTokens Optional cap on the page size read in.
     * @return array Tool definition of type web_fetch.
     */
    public static function webFetch(
        array $aAllowedDomains = [],
        array $aBlockedDomains = [],
        ?int  $iMaxUses = null,
        ?bool $bCitations = null,
        ?int  $iMaxContentTokens = null,
    ): array {
        $aResult = ['_type' => ToolType::WebFetch->value];
        if (!empty($aAllowedDomains))    $aResult['allowed_domains']    = $aAllowedDomains;
        if (!empty($aBlockedDomains))    $aResult['blocked_domains']    = $aBlockedDomains;
        if ($iMaxUses !== null)          $aResult['max_uses']           = $iMaxUses;
        if ($bCitations !== null)        $aResult['citations']          = $bCitations;
        if ($iMaxContentTokens !== null) $aResult['max_content_tokens'] = $iMaxContentTokens;
        return $aResult;
    }

    /**
     * Custom function the model can request a call to. The call comes back as
     * a ChatOutput of type FunctionCall and is answered via Context::tool().
     *
     * @param string $sName        Function name the model calls it by.
     * @param string $sDescription Description of what the function is for.
     * @param array  $aParameters  JSON schema of the expected parameters.
     * @return array Tool definition of type function.
     */
    public static function function(
        string $sName,
        string $sDescription,
        array  $aParameters,
    ): array {
        return [
            '_type'       => ToolType::Function->value,
            'name'        => $sName,
            'description' => $sDescription,
            'parameters'  => $aParameters,
        ];
    }

    /**
     * Image generation inside a text request — the images come back as
     * ChatOutput of type Image (for pure image requests use the Image endpoint).
     *
     * @param string|null $sModel             Optional image model.
     * @param int|null    $iWidth             Optional image width in pixels (pass together with $iHeight).
     * @param int|null    $iHeight            Optional image height in pixels (pass together with $iWidth).
     * @param string|null $sQuality           Optional quality level.
     * @param string|null $sBackground        Optional background (e.g. 'transparent').
     * @param string|null $sModeration        Optional content moderation level.
     * @param string|null $sInputFidelity     Optional: how closely to follow input images.
     * @param string|null $sOutputFormat      Optional file format (png, jpeg, webp).
     * @param int|null    $iOutputCompression Optional compression in percent.
     * @param int|null    $iPartialImages     Optional number of intermediate images.
     * @return array Tool definition of type image_generation.
     */
    public static function imageGeneration(
        ?string $sModel = null,
        ?int    $iWidth = null,
        ?int    $iHeight = null,
        ?string $sQuality = null,
        ?string $sBackground = null,
        ?string $sModeration = null,
        ?string $sInputFidelity = null,
        ?string $sOutputFormat = null,
        ?int    $iOutputCompression = null,
        ?int    $iPartialImages = null,
    ): array {
        $aResult = ['_type' => ToolType::ImageGeneration->value];
        if ($sModel !== null)            $aResult['model']              = $sModel;
        if ($iWidth !== null)            $aResult['width']              = $iWidth;
        if ($iHeight !== null)           $aResult['height']             = $iHeight;
        if ($sQuality !== null)          $aResult['quality']            = $sQuality;
        if ($sBackground !== null)       $aResult['background']         = $sBackground;
        if ($sModeration !== null)       $aResult['moderation']         = $sModeration;
        if ($sInputFidelity !== null)    $aResult['input_fidelity']     = $sInputFidelity;
        if ($sOutputFormat !== null)     $aResult['output_format']      = $sOutputFormat;
        if ($iOutputCompression !== null)$aResult['output_compression'] = $iOutputCompression;
        if ($iPartialImages !== null)    $aResult['partial_images']     = $iPartialImages;
        return $aResult;
    }

    /**
     * Connection to a remote MCP server whose tools the model may also use.
     *
     * Both providers run the server call themselves and feed the result back
     * into the same turn — the tool call and its result arrive as McpCall /
     * McpResult output blocks, no round trip via Context::tool() is needed.
     *
     * The neutral definition carries the union of what both providers accept;
     * each adapter uses only the fields its API knows:
     *   - Anthropic (Messages API, beta mcp-client-2025-11-20): name, url,
     *     authorization_token, allowed_tools (as an allowlist). It has no
     *     approval step, so require_approval / headers / description /
     *     connector_id are ignored.
     *   - OpenAI (Responses API): every field. allowed_tools and
     *     require_approval also accept their object forms unchanged.
     *
     * @param string      $sName               Name / server_label the server is addressed by.
     * @param string      $sUrl                 HTTPS endpoint of the MCP server. May be '' when $sConnectorId is set (OpenAI).
     * @param string      $sRequireApproval    Whether calls must be confirmed ('always', 'never'); OpenAI only.
     * @param array       $aAllowedTools       Optional restriction to individual server tools.
     * @param string|null $sAuthorizationToken Optional OAuth bearer token for authenticated servers.
     * @param array       $aHeaders            Optional extra HTTP headers sent to the server; OpenAI only.
     * @param string|null $sDescription        Optional hint describing the server's capabilities; OpenAI only.
     * @param string|null $sConnectorId        Optional OpenAI built-in connector id (e.g. 'connector_dropbox'); OpenAI only.
     * @return array Tool definition of type mcp.
     */
    public static function mcp(
        string  $sName,
        string  $sUrl,
        string  $sRequireApproval = 'always',
        array   $aAllowedTools = [],
        ?string $sAuthorizationToken = null,
        array   $aHeaders = [],
        ?string $sDescription = null,
        ?string $sConnectorId = null,
    ): array {
        $aResult = [
            '_type'            => ToolType::Mcp->value,
            'name'             => $sName,
            'url'              => $sUrl,
            'require_approval' => $sRequireApproval,
        ];
        if (!empty($aAllowedTools))            $aResult['allowed_tools']       = $aAllowedTools;
        if ($sAuthorizationToken !== null)     $aResult['authorization_token'] = $sAuthorizationToken;
        if (!empty($aHeaders))                 $aResult['headers']             = $aHeaders;
        if ($sDescription !== null)            $aResult['description']         = $sDescription;
        if ($sConnectorId !== null)            $aResult['connector_id']        = $sConnectorId;
        return $aResult;
    }

    /**
     * Approximate location for web search so regional results are preferred.
     * Passed as $aUserLocation to webSearch().
     *
     * @param string      $sCountry  Country code (e.g. 'DE').
     * @param string|null $sCity     Optional city.
     * @param string|null $sRegion   Optional region / state.
     * @param string|null $sTimezone Optional timezone (e.g. 'Europe/Berlin').
     * @return array Location structure for webSearch().
     */
    public static function location(
        string  $sCountry,
        ?string $sCity = null,
        ?string $sRegion = null,
        ?string $sTimezone = null,
    ): array {
        $aResult = ['type' => 'approximate', 'country' => $sCountry];
        if ($sCity !== null)     $aResult['city']     = $sCity;
        if ($sRegion !== null)   $aResult['region']   = $sRegion;
        if ($sTimezone !== null) $aResult['timezone'] = $sTimezone;
        return $aResult;
    }
}
