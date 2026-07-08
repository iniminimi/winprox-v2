<?php

namespace App\Enums;

enum EsgIndicatorType: string
{
    case Numeric = 'numeric';
    case Boolean = 'boolean';
    case String = 'string';
    case Json = 'json';

    public function valueColumn(): string
    {
        return match ($this) {
            self::Numeric => 'value_numeric',
            self::Boolean => 'value_boolean',
            self::String => 'value_string',
            self::Json => 'value_json',
        };
    }
}
