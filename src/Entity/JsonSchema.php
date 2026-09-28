<?php

namespace Conduit\Entity;

use Conduit\Enum\JsonSchemaType;

/**
 * Fluent builder for a JSON Schema, passed to Chat::jsonSchema().
 *
 * Building the array by hand gets tedious once a schema nests (properties
 * inside properties, items inside arrays) — this composes it from typed
 * pieces instead. build() returns the plain array Chat::jsonSchema() already
 * accepts, so passing a hand-written array directly still works unchanged.
 *
 * additionalProperties defaults to false and property() marks a property
 * required by default — that's the strict-mode shape OpenAI and Mistral
 * enforce for structured outputs; Anthropic accepts the same shape. A
 * property that may genuinely be unknown should stay required and be
 * marked nullable() instead — otherwise the model has no way to say "not
 * present" and tends to guess a value that merely fits the type.
 */
class JsonSchema
{
    private string  $sType;
    private ?string $sDescription = null;
    private array   $aProperties  = [];
    private array   $aRequired    = [];
    private bool    $bAdditionalProperties = false;
    private ?self   $oItems = null;
    private array   $aEnum  = [];
    private bool    $bNullable = false;

    private function __construct(string $sType)
    {
        $this->sType = $sType;
    }

    /** @return self A schema of type object — the only type providers accept as the root. */
    public static function object(): self { return new self('object'); }

    /** @return self A schema of type string, for use as a property() value or array() items. */
    public static function string(): self { return new self('string'); }

    /** @return self A schema of type number, for use as a property() value or array() items. */
    public static function number(): self { return new self('number'); }

    /** @return self A schema of type integer, for use as a property() value or array() items. */
    public static function integer(): self { return new self('integer'); }

    /** @return self A schema of type boolean, for use as a property() value or array() items. */
    public static function boolean(): self { return new self('boolean'); }

    /**
     * @param self $oItems Schema every array element must match.
     * @return self A schema of type array.
     */
    public static function array(self $oItems): self
    {
        $oInstance = new self('array');
        $oInstance->oItems = $oItems;
        return $oInstance;
    }

    /**
     * @param array         $aValues Allowed values.
     * @param JsonSchemaType $oType  Underlying type of the values (default String).
     * @return self A schema restricted to a fixed set of values.
     */
    public static function enum(array $aValues, JsonSchemaType $oType = JsonSchemaType::String): self
    {
        $oInstance = new self($oType->value);
        $oInstance->aEnum = $aValues;
        return $oInstance;
    }

    /**
     * @param string $sDescription Hint for the model on what belongs here.
     * @return self
     */
    public function description(string $sDescription): self
    {
        $this->sDescription = $sDescription;
        return $this;
    }

    /**
     * Allows null as this schema's value, on top of its own type — so the
     * model can honestly answer "not present" instead of guessing a value
     * that fits the type. Use this for a property that isn't reliably
     * derivable from the input, together with property($bRequired: true)
     * (the default): 'strict' JSON schema requires every property to be
     * listed in `required`, so real optionality is expressed through a
     * nullable type, not through leaving the property out of `required`.
     *
     * @return self
     */
    public function nullable(): self
    {
        $this->bNullable = true;
        return $this;
    }

    /**
     * Adds a property to an object schema. Required by default, because
     * 'strict' JSON schema (OpenAI, Mistral) rejects a schema whose
     * `required` doesn't list every key in `properties` — mark a property
     * nullable: true instead of passing $bRequired: false to express that its
     * value may genuinely be unknown.
     *
     * A plain scalar property (string, integer, ...) only needs its
     * JsonSchemaType, not a JsonSchema::string()-style sub-builder instance —
     * pass a full JsonSchema instance instead when the property itself nests
     * (object, array) or needs an enum/description of its own.
     *
     * @param string               $sName      Property name.
     * @param JsonSchemaType|self  $mType      Plain type, or a nested JsonSchema for
     *                                         object/array/enum/description properties.
     * @param bool                 $bRequired  Whether the property is listed in `required`.
     * @param bool                 $bNullable  Whether the property additionally allows null.
     * @return self
     */
    public function property(string $sName, JsonSchemaType|self $mType, bool $bRequired = true, bool $bNullable = false): self
    {
        $oSchema = $mType instanceof self ? $mType : new self($mType->value);
        if ($bNullable) {
            $oSchema->nullable();
        }
        $this->aProperties[$sName] = $oSchema;
        if ($bRequired && !in_array($sName, $this->aRequired, true)) {
            $this->aRequired[] = $sName;
        }
        return $this;
    }

    /**
     * @param bool $bAllowed Whether properties outside the schema are allowed (default false).
     * @return self
     */
    public function additionalProperties(bool $bAllowed): self
    {
        $this->bAdditionalProperties = $bAllowed;
        return $this;
    }

    /**
     * @return array The schema as a plain array, ready for Chat::jsonSchema().
     */
    public function build(): array
    {
        $aResult = ['type' => $this->bNullable ? [$this->sType, 'null'] : $this->sType];
        if ($this->sDescription !== null) {
            $aResult['description'] = $this->sDescription;
        }
        if ($this->aEnum !== []) {
            // Type alone doesn't exempt a value from the enum check — null
            // must be listed here too, or a nullable enum still rejects null.
            $aResult['enum'] = $this->bNullable ? [...$this->aEnum, null] : $this->aEnum;
        }
        if ($this->sType === 'object') {
            $aProperties = [];
            foreach ($this->aProperties as $sName => $oSchema) {
                $aProperties[$sName] = $oSchema->build();
            }
            $aResult['properties']           = $aProperties;
            $aResult['required']             = $this->aRequired;
            $aResult['additionalProperties'] = $this->bAdditionalProperties;
        }
        if ($this->sType === 'array' && $this->oItems !== null) {
            $aResult['items'] = $this->oItems->build();
        }
        return $aResult;
    }
}
