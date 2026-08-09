<?php

namespace App\Enums;
enum GameMode : string{
    case GAME_MODE = 'gameMode';
    case VS_AI = 'vs_ai';
    case PRACTICE = 'practice';
    case SOLO = 'solo';
    case PVP = 'pvp';
    case MULTIPLAYER = 'multiplayer';
        
    /**
        * Get an array of all HTTP Methods values.
        *
        * @return string[]
    */
    public static function values(): array {
        return array_column(self::cases(), 'value');
    }
}