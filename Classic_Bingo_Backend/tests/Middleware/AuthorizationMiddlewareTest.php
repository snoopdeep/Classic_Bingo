<?php

namespace App\Tests\Middleware;

use App\Core\Request;
use App\Enums\ErrorCode;
use App\Enums\UserRole;
use App\Handlers\AppException;
use App\Middleware\AuthorizationMiddleware;
use App\Services\AuthorizationService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit test suite for the AuthorizationMiddleware.
 *
 * @covers \App\Middleware\AuthorizationMiddleware
 */
final class AuthorizationMiddlewareTest extends TestCase
{
    private MockObject|AuthorizationService $authzServiceMock;
    private MockObject|Request $requestMock;
    private AuthorizationMiddleware $middleware;

    /**
     * Sets up the mock dependencies and the middleware instance before each test.
     */
    protected function setUp(): void
    {
        // 1. Mock the service dependency that contains the authorization logic
        $this->authzServiceMock = $this->createMock(AuthorizationService::class);

        // 2. Mock the Request object to control its return values
        $this->requestMock = $this->createMock(Request::class);

        // 3. Instantiate the middleware with its mocked dependency
        $this->middleware = new AuthorizationMiddleware($this->authzServiceMock);
    }

    // ===================================================================
    // Failure Scenarios
    // ===================================================================

    #[Test]
    public function it_throws_exception_for_invalid_role_in_route_definition(): void
    {
        // Assert: Expect an InvalidArgumentException for a developer configuration error
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid role 'not-a-real-role' provided in route definition.");

        // Act: Call the handle method with a role that doesn't exist in the UserRole enum
        $this->middleware->handle($this->requestMock, 'not-a-real-role');
    }

    #[Test]
    public function it_throws_exception_if_user_is_not_authenticated(): void
    {
        // Arrange: Simulate that the AuthenticationMiddleware did not run or failed
        $this->requestMock->method('getAuthUser')->willReturn(null);

        // Assert: Expect an AppException indicating an invalid token
        $this->expectException(AppException::class);
        $this->expectExceptionCode(401);

        // Act
        $this->middleware->handle($this->requestMock, UserRole::ADMIN->value);
    }

    #[Test]
    public function it_throws_forbidden_exception_if_user_lacks_required_role(): void
    {
        // Arrange
        $tokenData = $this->createTokenData('user-123', UserRole::USER->value);
        $this->requestMock->method('getAuthUser')->willReturn($tokenData);

        // Configure mock to explicitly deny the 'admin' role
        $this->authzServiceMock
            ->method('hasRole')
            ->with($tokenData, UserRole::ADMIN->value)
            ->willReturn(false);

        // Assert: Expect a 403 Forbidden error
        $this->expectException(AppException::class);
        $this->expectExceptionCode(403);

        // Act
        $this->middleware->handle($this->requestMock, UserRole::ADMIN->value);
    }

    #[Test]
    public function it_throws_forbidden_exception_if_user_is_not_the_owner(): void
    {
        // Arrange
        $tokenData = $this->createTokenData('user-123', UserRole::USER->value); // User ID is 123
        $this->requestMock->method('getAuthUser')->willReturn($tokenData);
        $this->requestMock->method('getRouteParams')->willReturn(['userId' => 'user-456']); // Resource ID is 456

        // Configure mock to explicitly deny ownership
        $this->authzServiceMock
            ->method('isOwner')
            ->with($tokenData, 'user-456')
            ->willReturn(false);

        // Assert: Expect a 403 Forbidden error
        $this->expectException(AppException::class);
        $this->expectExceptionCode(403);

        // Act
        $this->middleware->handle($this->requestMock, UserRole::OWNER->value);
    }

    // ===================================================================
    // Success Scenarios
    // ===================================================================

    #[Test]
    public function it_succeeds_if_user_has_required_admin_role(): void
    {
        // Arrange
        $tokenData = $this->createTokenData('admin-1', UserRole::ADMIN->value);
        $this->requestMock->method('getAuthUser')->willReturn($tokenData);

        // Configure mock to grant access for the 'admin' role
        $this->authzServiceMock
            ->method('hasRole')
            ->with($tokenData, UserRole::ADMIN->value)
            ->willReturn(true);

        // Act: Run the middleware
        $this->middleware->handle($this->requestMock, UserRole::ADMIN->value);

        // Assert: If no exception is thrown, the test passes.
        $this->assertTrue(true);
    }

    #[Test]
    public function it_succeeds_if_user_is_the_owner(): void
    {
        // Arrange
        $userId = 'user-123';
        $tokenData = $this->createTokenData($userId, UserRole::USER->value);
        $this->requestMock->method('getAuthUser')->willReturn($tokenData);
        $this->requestMock->method('getRouteParams')->willReturn(['userId' => $userId]); // User ID matches resource ID

        // Configure mock to grant ownership
        $this->authzServiceMock
            ->method('isOwner')
            ->with($tokenData, $userId)
            ->willReturn(true);

        // Act
        $this->middleware->handle($this->requestMock, UserRole::OWNER->value);

        // Assert
        $this->assertTrue(true);
    }

    #[Test]
    public function it_succeeds_if_user_is_owner_when_owner_or_admin_is_required(): void
    {
        // Arrange
        $userId = 'user-123';
        $tokenData = $this->createTokenData($userId, UserRole::USER->value);
        $this->requestMock->method('getAuthUser')->willReturn($tokenData);
        $this->requestMock->method('getRouteParams')->willReturn(['userId' => $userId]);

        // Mock the service to confirm ownership
        $this->authzServiceMock->method('isOwner')->willReturn(true);

        // Crucially, the 'hasRole' check for admin should never be called because the loop will break early.
        $this->authzServiceMock->expects($this->never())->method('hasRole');

        // Act
        $this->middleware->handle($this->requestMock, UserRole::OWNER->value, UserRole::ADMIN->value);

        // Assert
        $this->assertTrue(true);
    }

    #[Test]
    public function it_succeeds_if_user_is_admin_when_owner_or_admin_is_required(): void
    {
        // Arrange
        $userId = 'admin-1';
        $tokenData = $this->createTokenData($userId, UserRole::ADMIN->value);
        $this->requestMock->method('getAuthUser')->willReturn($tokenData);
        $this->requestMock->method('getRouteParams')->willReturn(['userId' => 'user-456']); // User is not the owner

        // Mock the service to deny ownership but grant admin role
        $this->authzServiceMock->method('isOwner')->willReturn(false);
        $this->authzServiceMock->method('hasRole')->with($tokenData, UserRole::ADMIN->value)->willReturn(true);

        // Act
        $this->middleware->handle($this->requestMock, UserRole::OWNER->value, UserRole::ADMIN->value);

        // Assert
        $this->assertTrue(true);
    }

    // ===================================================================
    // Helper Method
    // ===================================================================

    /**
     * Creates a simple stdClass object to simulate decoded token data.
     */
    private function createTokenData(string $userId, string $role): stdClass
    {
        $data = new stdClass();
        $data->sub = $userId;
        $data->role = $role;
        return $data;
    }
}
