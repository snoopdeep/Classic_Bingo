<?php
namespace App\Utils;

use Ramsey\Uuid\Uuid;

/**
 * UUID Generator utility class
 * Renamed from 'UUID' to 'UUIDGenerator' to avoid conflicts with Ramsey\Uuid\Uuid
 */
class UUIDGenerator 
{
    /**
     * Generate a new UUID v4
     */
    public static function generate(): string 
    {
        return Uuid::uuid4()->toString();
    }
    
    /**
     * Validate if a string is a valid UUID
     */
    public static function isValid(string $uuid): bool 
    {
        return Uuid::isValid($uuid);
    }

    /**
     * Generate a UUID without hyphens
     */
    public static function generateCompact(): string 
    {
        return str_replace('-', '', self::generate());
    }

    /**
     * Convert a compact UUID back to standard format
     */
    public static function addHyphens(string $compactUuid): string 
    {
        if (strlen($compactUuid) !== 32) {
            throw new \InvalidArgumentException('Compact UUID must be 32 characters long');
        }
        
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($compactUuid, 0, 8),
            substr($compactUuid, 8, 4),
            substr($compactUuid, 12, 4),
            substr($compactUuid, 16, 4),
            substr($compactUuid, 20, 12)
        );
    }
}
