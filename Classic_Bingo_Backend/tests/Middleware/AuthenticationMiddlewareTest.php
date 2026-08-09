<?php

namespace App\Tests\Middleware;

use App\Config\JwtConfig;
use App\Core\Request;
use App\Enums\ErrorCode;
use App\Handlers\AppException;
use App\Middleware\AuthenticationMiddleware;
use App\Constants\ServerKeys;
use Firebase\JWT\ExpiredException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit test suite for the AuthenticationMiddleware.
 *
 * @covers \App\Middleware\AuthenticationMiddleware
 */
final class AuthenticationMiddlewareTest extends TestCase
{
    private JwtConfig $jwtConfig;
    private MockObject|Request $requestMock;
    private AuthenticationMiddleware $middleware;
    private string $testSecret = 'a-secure-secret-for-testing';

    /**
     * Sets up dependencies for each test.
     */
    protected function setUp(): void {
        // 1. Create a valid config object
        $this->jwtConfig = new JwtConfig([
            'secret' => $this->testSecret,
            'access_token_expiration' => 3600,
            'refresh_token_expiration' => 86400,
        ]);

        // 2. Create the middleware instance with the config
        $this->middleware = new AuthenticationMiddleware($this->jwtConfig);

        // 3. Create a mock for the Request object that will be passed to the handle method
        $this->requestMock = $this->createMock(Request::class);

        // 4. Ensure a clean state by clearing the SERVER superglobal
        $_SERVER = [];
    }

    /**
     * Cleans up the state after each test.
     */
    protected function tearDown(): void
    {
        $_SERVER = [];
    }

    // ===================================================================
    // Failure Scenarios
    // ===================================================================

    #[Test]
    public function it_throws_exception_if_token_is_missing(): void
    {
        // Assert: Expect an AppException with a 401 status code
        $this->expectException(AppException::class);
        $this->expectExceptionCode(401);
        
        try {
            // Act: Handle the request with no Authorization header set
            $this->middleware->handle($this->requestMock);
        } catch (AppException $e) {
            // Also assert the specific error code enum is correct
            $this->assertEquals(ErrorCode::AUTH_ACCESS_TOKEN_MISSING, $e->errorCode);
            throw $e;
        }
    }

    #[Test]
    public function it_throws_exception_if_token_is_invalid_or_malformed(): void
    {
        // Arrange: Provide a token that is not a valid JWT
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Bearer this.is.not.a.valid.token';

        // Assert
        $this->expectException(AppException::class);
        $this->expectExceptionCode(401);

        try {
            // Act
            $this->middleware->handle($this->requestMock);
        } catch (AppException $e) {
            $this->assertEquals(ErrorCode::AUTH_ACCESS_TOKEN_INVALID, $e->errorCode);
            throw $e;
        }
    }

    #[Test]
    public function it_rethrows_expired_exception_if_token_is_expired(): void
    {
        // Arrange: Generate a token that has already expired
        $expiredToken = $this->generateTestToken('user-456', 'user', -3600); // Expired 1 hour ago
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Bearer ' . $expiredToken;

        // Assert: Expect the specific ExpiredException from the underlying library,
        // because the middleware does not catch it explicitly. This is correct behavior.
        $this->expectException(ExpiredException::class);

        // Act
        $this->middleware->handle($this->requestMock);
    }

    // ===================================================================
    // Success Scenario
    // ===================================================================

    #[Test]
    public function it_sets_auth_user_on_request_with_valid_token(): void
    {
        // Arrange
        $userId = 'user-123';
        $userRole = 'admin';
        $validToken = $this->generateTestToken($userId, $userRole, 3600); // Expires in 1 hour
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Bearer ' . $validToken;

        // Create the expected payload object that will be attached to the request
        $expectedPayload = new stdClass();
        $expectedPayload->sub = $userId;
        $expectedPayload->role = $userRole;
        // The exact iat and exp don't need to match, just the core data. We use a callback.

        // Assert: We expect the setAuthUser method on our mock Request to be called exactly once.
        $this->requestMock
            ->expects($this->once())
            ->method('setAuthUser')
            // Use a callback to inspect the payload passed to the method
            ->with($this->callback(function (stdClass $payload) use ($userId, $userRole) {
                // Return true if the payload contains the correct user ID and role
                return $payload->sub === $userId && $payload->role === $userRole;
            }));

        // Act: Run the middleware's handle method
        $this->middleware->handle($this->requestMock);
    }

    // ===================================================================
    // Helper Method
    // ===================================================================

    /**
     * Helper to generate a JWT for testing purposes.
     */
    private function generateTestToken(string $userId, string $role, int $expiration): string
    {
        $payload = [
            'iat' => time(),
            'exp' => time() + $expiration,
            'sub' => $userId,
            'role' => $role,
        ];
        return \Firebase\JWT\JWT::encode($payload, $this->testSecret, 'HS256');
    }
}