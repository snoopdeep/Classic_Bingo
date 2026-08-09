<?php

namespace Tests\Unit\Services;

use App\Services\AuthorizationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit tests for the AuthorizationService class.
 */
class AuthorizationServiceTest extends TestCase {
    /**
     * Data provider for the testHasRole method.
     */
    public static function provideHasRoleData(): array {
        return [
            'user is admin' => [(object)['role' => 'admin'], 'admin', true],
            'user is not admin' => [(object)['role' => 'user'], 'admin', false],
            'token has no role property' => [(object)['sub' => 'user-123'], 'admin', false],
            'token is an empty object' => [new stdClass(), 'admin', false],
            'token is null' => [null, 'admin', false],
            'role is case sensitive' => [(object)['role' => 'Admin'], 'admin', false],
            'required role is different case' => [(object)['role' => 'admin'], 'Admin', false],
        ];
    }

    /**
     * Tests the hasRole method with various inputs.
     */
    #[DataProvider('provideHasRoleData')]
    public function testHasRole(?stdClass $tokenData, string $role, bool $expectedResult): void {
          // Arrange
        $service = new AuthorizationService();
        // Act
        $actualResult = $service->hasRole($tokenData, $role);
        // Assert
        $this->assertSame($expectedResult, $actualResult);
    }

    /**
     * Data provider for the testIsOwner method.
     */
    public static function provideIsOwnerData(): array {
        
        $userId = 'user-abc-123';
        return [
            'user is the owner' => [(object)['sub' => $userId], $userId, true],
            'user is not the owner' => [(object)['sub' => 'user-def-456'], $userId, false],
            'token has no sub property' => [(object)['role' => 'admin'], $userId, false],
            'token is an empty object' => [new stdClass(), $userId, false],
            'token is null' => [null, $userId, false],
            'ID from token is int, resource ID is string' => [(object)['sub' => 123], '123', false],
            'ID from token is string, resource ID is int' => [(object)['sub' => '123'], 123, false],
        ];
    }

    /**
     * Tests the isOwner method with various inputs.
     */
    #[DataProvider('provideIsOwnerData')]
    public function testIsOwner(?stdClass $tokenData, string|int $resourceUserId, bool $expectedResult): void {
        // Arrange
        $service = new AuthorizationService();

        // Act
        $actualResult = $service->isOwner($tokenData, $resourceUserId);

        // Assert
        $this->assertSame($expectedResult, $actualResult);
    }
}

