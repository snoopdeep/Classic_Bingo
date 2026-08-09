<?php

namespace App\Constants;

final class GameConfigKeys{
    /** Private constructor to prevent instantiation. */
    private function __construct() {}

    public const CALL_INTERVAL = 'call_interval';
    public const MODES = 'modes';
    public const BASE_COST = 'baseCost';
    public const EXTRA_CARD_COST = 'extraCardCost';
    public const PATTERNS = 'patterns';
    public const PATTERN_METADATA = 'pattern_metadata';
    public const PRACTICE = 'practice';
}