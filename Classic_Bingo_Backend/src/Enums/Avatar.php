<?php

namespace App\Enums;

enum Avatar: string
{
    case AVATAR_01 = 'avatar_01';
    case AVATAR_02 = 'avatar_02';
    case AVATAR_03 = 'avatar_03';
    case AVATAR_04 = 'avatar_04';
    case AVATAR_05 = 'avatar_05';
    
    // case AVATAR_01 = 'avatar_01';
    // case AVATAR_02 = 'avatar_02';
    // case AVATAR_03 = 'avatar_03';
    // case AVATAR_04 = 'avatar_04';
    // case AVATAR_05 = 'avatar_05';
    // case DEFAULT = 'avatar_01';

    /**
     * Returns the default Avatar case.
     */
    public static function default(): self
    {
        return self::AVATAR_01;
    }

    /**
     * Get an array of all avatar values.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}