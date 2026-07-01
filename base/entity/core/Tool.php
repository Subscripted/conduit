<?php

namespace entity\core;

class Tool
{
    public static function webSearch(
        array  $allowedDomains = [],
        array  $blockedDomains = [],
        ?int   $maxUses = null,
        ?array $userLocation = null,
        string $contextSize = 'medium',
    ): array
    {
        return array_filter([
            '_prism_type' => 'web_search',
            'allowed_domains' => $allowedDomains ?: null,
            'blocked_domains' => $blockedDomains ?: null,
            'max_uses' => $maxUses,
            'user_location' => $userLocation,
            'context_size' => $contextSize,
        ]);
    }

    public static function mcp(
        string $name,
        string $url,
        string $requireApproval = 'always',
        array  $allowedTools = [],
    ): array
    {
        return array_filter([
            '_prism_type' => 'mcp',
            'name' => $name,
            'url' => $url,
            'require_approval' => $requireApproval,
            'allowed_tools' => $allowedTools ?: null,
        ]);
    }

    public static function function (
        string $name,
        string $description,
        array  $parameters,
    ): array
    {
        return [
            '_prism_type' => 'function',
            'name' => $name,
            'description' => $description,
            'parameters' => $parameters,
        ];
    }

    public static function location(
        string  $country,
        ?string $city = null,
        ?string $region = null,
        ?string $timezone = null,
    ): array
    {
        return array_filter([
            'type' => 'approximate',
            'country' => $country,
            'city' => $city,
            'region' => $region,
            'timezone' => $timezone,
        ]);
    }
}