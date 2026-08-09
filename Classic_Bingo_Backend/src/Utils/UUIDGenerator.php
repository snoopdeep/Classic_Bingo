<?php
namespace App\Utils;

use Ramsey\Uuid\Uuid;

/**
 * UUID Generator utility class
 * Renamed from 'UUID' to 'UUIDGenerator' to avoid conflicts with Ramsey\Uuid\Uuid
 */
class UUIDGenerator {
    /**
     * Generate a new UUID v4
     */
    public static function generate(): string {
        return Uuid::uuid4()->toString();
    }
    
    /**
     * Validates the string standard representation of a UUID.
     * @return bool
     */
    public static function isValidUUID(string $uuid):bool{
       return Uuid::isValid($uuid);
    }

}
