<?php

namespace Conduit\Enum;

/**
 * JSON Schema primitive types, passed to JsonSchema::property() for a plain
 * scalar field so it doesn't need its own JsonSchema::string()-style
 * sub-builder instance. Object and Array are still only useful together with
 * a full JsonSchema instance (property()/items() need somewhere to nest) —
 * they're listed here for completeness of the JSON Schema type vocabulary.
 */
enum JsonSchemaType: string
{
    case String  = 'string';
    case Number  = 'number';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Object  = 'object';
    case Array   = 'array';
}
