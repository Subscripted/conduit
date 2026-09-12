<?php

namespace Conduit\Enum;

use Conduit\Exception\ConfigurationException;

/**
 * Selectable model providers.
 *
 * Decides which adapter the AdapterFactory builds. A new provider needs a
 * case here, a pattern in PATTERNS below, plus the matching branch in the
 * AdapterFactory.
 */
enum AIProvider
{
    case OpenAI;
    case Anthropic;
    case Google;

    /**
     * Model id prefixes/patterns per provider, checked in order. This is a
     * maintenance point: a new model family needs a new entry here once it
     * doesn't match an existing pattern.
     *
     * @var array<string, self>
     */
    private const array PATTERNS = [
        '/^claude-/i'                            => self::Anthropic,
        '/^(gpt-|o\d|chatgpt-|ft:gpt-|omni-)/i'  => self::OpenAI,
        '/^(gemini-|palm-|bison)/i'               => self::Google,
    ];

    /**
     * Determines the provider a model id belongs to.
     *
     * @param string $sModel Model id, e.g. 'claude-opus-4-5', 'gpt-5.6-luna'.
     * @return self Matching provider.
     * @throws ConfigurationException If no pattern matches this model id.
     */
    public static function fromModel(string $sModel): self
    {
        foreach (self::PATTERNS as $sPattern => $oProvider) {
            if (preg_match($sPattern, $sModel) === 1) {
                return $oProvider;
            }
        }
        throw new ConfigurationException("Cannot determine provider for model '$sModel'.");
    }
}
