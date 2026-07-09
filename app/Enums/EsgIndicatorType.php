<?php

namespace App\Enums;

enum EsgIndicatorType: string
{
    case Numeric = 'numeric';
    case Boolean = 'boolean';
    case String = 'string';
    case Choice = 'choice';
    case MultiChoice = 'multi_choice';
    case Json = 'json';

    public function valueColumn(): string
    {
        return match ($this) {
            self::Numeric => 'value_numeric',
            self::Boolean => 'value_boolean',
            self::String, self::Choice => 'value_string',
            self::MultiChoice, self::Json => 'value_json',
        };
    }

    public function usesOptionList(): bool
    {
        return match ($this) {
            self::Choice, self::MultiChoice => true,
            default => false,
        };
    }
}
