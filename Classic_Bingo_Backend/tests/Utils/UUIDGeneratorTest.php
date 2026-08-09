<?php

namespace App\Tests\Utils; 

use App\Utils\UUIDGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UUIDGenerator::class)]
class UUIDGeneratorTest extends TestCase {
    #[Test]
    public function generateReturnsValidUUIDString(): void {
        $uuid = UUIDGenerator::generate();

        // Check 1:  This regex validates the 8-4-4-4-12 structure of a UUID.
        $pattern = '/^[a-f\d]{8}-(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i';
        $this->assertMatchesRegularExpression($pattern, $uuid, "Generated string is not a valid UUID format.");

        // Check 2; Leverage the class's own validator for an additional check.
        $this->assertTrue(UUIDGenerator::isValidUUID($uuid), "Generated UUID fails its own validation check.");
    }

    #[Test]
    #[DataProvider('provideUUIDsForValidation')]
    public function isValidUUID(string $uuid, bool $expectedResult): void {
        $this->assertSame($expectedResult, UUIDGenerator::isValidUUID($uuid));
    }

    /**
     * Data provider for isValidUUID test.
     */
    public static function provideUUIDsForValidation(): array {
        return [
            'valid v4 uuid' => ['f47ac10b-58cc-4372-a567-0e02b2c3d479', true],
            'valid v1 uuid' => ['6ba7b810-9dad-11d1-80b4-00c04fd430c8', true],
            'uppercase valid uuid' => ['F47AC10B-58CC-4372-A567-0E02B2C3D479', true],
            'invalid format - too short' => ['f47ac10b-58cc-4372-a567-0e02b2c3d47', false],
            'invalid format - wrong characters' => ['f47ac10b-58cc-4372-a567-0e02b2c3d47g', false],
            'invalid format - no hyphens' => ['f47ac10b58cc4372a5670e02b2c3d479', false],
            'empty string' => ['', false],
            'random string' => ['this-is-not-a-uuid', false],
        ];
    }
}