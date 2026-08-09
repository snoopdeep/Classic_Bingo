<?php

namespace App\Utils;
/**
 * A utility class for generating random numbers.
 *
 * Provides a centralized way to generate both standard and
 * cryptographically secure random integers.
 */
class Randomizer
{
    /**
     * Generates a random integer.
     *
     * @param int $min The lowest value to return.
     * @param int $max The highest value to return.
     * @param bool $is_secure If true, uses a cryptographically secure generator.
     * @return int A random integer within the specified range.
     */
    public static function int(int $min, int $max, bool $is_secure = false): int
    {
        if ($is_secure) {
            // Use this for anything security-related!
            return random_int($min, $max);
        } else {
            // Fine for non-critical, non-security uses.
            return rand($min, $max);
        }
    }
}
