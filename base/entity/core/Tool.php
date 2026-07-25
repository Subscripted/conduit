<?php

namespace entity\core;

class Tool
{
    public static function webSearch(
        array  $aAllowedDomains = [],
        array  $aBlockedDomains = [],
        ?int   $iMaxUses = null,
        ?array $aUserLocation = null,
        string $sContextSize = 'medium',
    ): array {
        $aResult = ['_type' => 'web_search', 'context_size' => $sContextSize];
        if (!empty($aAllowedDomains)) $aResult['allowed_domains'] = $aAllowedDomains;
        if (!empty($aBlockedDomains)) $aResult['blocked_domains'] = $aBlockedDomains;
        if ($iMaxUses !== null)       $aResult['max_uses']        = $iMaxUses;
        if ($aUserLocation !== null)  $aResult['user_location']   = $aUserLocation;
        return $aResult;
    }

    public static function function(
        string $sName,
        string $sDescription,
        array  $aParameters,
    ): array {
        return [
            '_type'       => 'function',
            'name'        => $sName,
            'description' => $sDescription,
            'parameters'  => $aParameters,
        ];
    }

    public static function imageGeneration(
        ?string $sModel = null,
        ?string $sSize = null,
        ?string $sQuality = null,
        ?string $sBackground = null,
        ?string $sModeration = null,
        ?string $sInputFidelity = null,
        ?string $sOutputFormat = null,
        ?int    $iOutputCompression = null,
        ?int    $iPartialImages = null,
    ): array {
        $aResult = ['_type' => 'image_generation'];
        if ($sModel !== null)            $aResult['model']              = $sModel;
        if ($sSize !== null)             $aResult['size']               = $sSize;
        if ($sQuality !== null)          $aResult['quality']            = $sQuality;
        if ($sBackground !== null)       $aResult['background']         = $sBackground;
        if ($sModeration !== null)       $aResult['moderation']         = $sModeration;
        if ($sInputFidelity !== null)    $aResult['input_fidelity']     = $sInputFidelity;
        if ($sOutputFormat !== null)     $aResult['output_format']      = $sOutputFormat;
        if ($iOutputCompression !== null)$aResult['output_compression'] = $iOutputCompression;
        if ($iPartialImages !== null)    $aResult['partial_images']     = $iPartialImages;
        return $aResult;
    }

    public static function mcp(
        string $sName,
        string $sUrl,
        string $sRequireApproval = 'always',
        array  $aAllowedTools = [],
    ): array {
        $aResult = [
            '_type'            => 'mcp',
            'name'             => $sName,
            'url'              => $sUrl,
            'require_approval' => $sRequireApproval,
        ];
        if (!empty($aAllowedTools)) {
            $aResult['allowed_tools'] = $aAllowedTools;
        }
        return $aResult;
    }

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
