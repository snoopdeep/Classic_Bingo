<?php

namespace App\Tests\Services;

use App\Enums\ErrorCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\Test;
use App\Services\AuthenticationService;
use App\Models\User;
use App\Config\JwtConfig;
use App\Handlers\AppException;
use App\Handlers\ErrorCatalog;
use App\Constants\UserDataKeys;
use App\Constants\ServerKeys;
use App\Constants\CookieConstants;
use App\Constants\JwtConstants;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Test suite for the refreshAccessToken method in AuthenticationService.
 */
class AuthenticationServiceRefreshTokenTest extends TestCase {
    private MockObject|User $userModelMock;
    private JwtConfig $jwtConfig;
    private AuthenticationService $authService;
    private string $testSecretKey = 'test-secret-key-for-jwt';

    protected function setUp(): void {
        $this->userModelMock = $this->createMock(User::class);
        $this->jwtConfig = new JwtConfig([
            'secret' => $this->testSecretKey,
            'access_token_expiration' => 3600,
            'refresh_token_expiration' => 86400
        ]);
        $this->authService = new AuthenticationService($this->userModelMock, $this->jwtConfig);
        $_SERVER = [];
        $_COOKIE = [];
    }

    protected function tearDown(): void {
        $_SERVER = [];
        $_COOKIE = [];
    }

    // ==================================================================================
    // FAILURE TEST CASES
    // ==================================================================================

    #[Test]
    public function it_throws_exception_if_refresh_token_is_missing(): void {
        $this->expectException(AppException::class);
        $this->expectExceptionCode(ErrorCatalog::get(ErrorCode::AUTH_REFRESH_TOKEN_MISSING)['status']); // Asserts against the HTTP status code
        $this->authService->refreshAccessToken();
    }

    #[Test]
    public function it_throws_exception_if_access_token_is_missing(): void {
        $_COOKIE[CookieConstants::REFRESH_TOKEN] = 'some-refresh-token';
        $this->expectException(AppException::class);
        $this->expectExceptionCode(ErrorCatalog::get(ErrorCode::AUTH_ACCESS_TOKEN_MISSING)['status']);
        $this->authService->refreshAccessToken();
    }

    #[Test]
    public function it_throws_exception_if_access_token_is_invalid(): void {
        $_COOKIE[CookieConstants::REFRESH_TOKEN] = 'some-refresh-token';
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Bearer invalid.malformed.token';
        $this->expectException(AppException::class);
        $this->expectExceptionCode(ErrorCatalog::get(ErrorCode::AUTH_ACCESS_TOKEN_INVALID)['status']);
        $this->authService->refreshAccessToken();
    }

    #[Test]
    public function it_throws_exception_if_user_is_not_found(): void {
        $userId = 'non-existent-user-id';
        $expiredToken = $this->generateExpiredAccessToken($userId);
        $_COOKIE[CookieConstants::REFRESH_TOKEN] = 'some-refresh-token';
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Bearer ' . $expiredToken;
        $this->userModelMock->method('findForAuth')->with($userId)->willReturn(null);
        $this->expectException(AppException::class);
        $this->expectExceptionCode(403);
        $this->authService->refreshAccessToken();
    }

    #[Test]
    public function it_throws_exception_if_refresh_token_is_invalid(): void {
        $userId = 'test-user-id-123';
        $expiredToken = $this->generateExpiredAccessToken($userId);
        $providedRefreshToken = 'wrong-refresh-token';
        $storedRefreshTokenHash = password_hash('correct-refresh-token', PASSWORD_DEFAULT);
        $_COOKIE[CookieConstants::REFRESH_TOKEN] = $providedRefreshToken;
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Bearer ' . $expiredToken;
        $this->userModelMock->method('findForAuth')->with($userId)->willReturn([
            UserDataKeys::USER_ID => $userId,
            UserDataKeys::USER_ROLE => 'user',
            UserDataKeys::REFRESH_TOKEN => $storedRefreshTokenHash
        ]);
        $this->expectException(AppException::class);
        $this->expectExceptionCode(ErrorCatalog::get(ErrorCode::AUTH_REFRESH_TOKEN_INVALID_OR_REVOKED)['status']);
        $this->authService->refreshAccessToken();
    }

    #[Test]
    public function it_throws_exception_if_user_has_no_stored_refresh_token(): void {
        $userId = 'test-user-id-123';
        $expiredToken = $this->generateExpiredAccessToken($userId);
        $_COOKIE[CookieConstants::REFRESH_TOKEN] = 'some-refresh-token';
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Bearer ' . $expiredToken;
        $this->userModelMock->method('findForAuth')->with($userId)->willReturn([
            UserDataKeys::USER_ID => $userId,
            UserDataKeys::USER_ROLE => 'user',
            UserDataKeys::REFRESH_TOKEN => null
        ]);
        $this->expectException(AppException::class);
        $this->expectExceptionCode(ErrorCatalog::get(ErrorCode::AUTH_REFRESH_TOKEN_INVALID_OR_REVOKED)['status']);
        $this->authService->refreshAccessToken();
    }

    // ==================================================================================
    // SUCCESS TEST CASES
    // ==================================================================================

    #[Test]
    public function it_succeeds_with_a_valid_expired_access_token(): void {
        $userId = 'test-user-id-123';
        $userRole = 'admin';
        $expiredToken = $this->generateExpiredAccessToken($userId, $userRole);
        $refreshToken = 'valid-refresh-token-xyz';
        $refreshTokenHash = password_hash($refreshToken, PASSWORD_DEFAULT);
        $_COOKIE[CookieConstants::REFRESH_TOKEN] = $refreshToken;
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Bearer ' . $expiredToken;
        $this->userModelMock->method('findForAuth')->with($userId)->willReturn([
            UserDataKeys::USER_ID => $userId,
            UserDataKeys::USER_ROLE => $userRole,
            UserDataKeys::REFRESH_TOKEN => $refreshTokenHash
        ]);
        
        sleep(1); // Wait 1 second to ensure a new timestamp for the token
        
        $result = $this->authService->refreshAccessToken();
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('accessToken', $result['data']);
        $newAccessToken = $result['data']['accessToken'];
        $this->assertNotEquals($expiredToken, $newAccessToken);
        $decodedToken = JWT::decode($newAccessToken, new Key($this->testSecretKey, JwtConstants::ALGO_HS256));
        $this->assertEquals($userId, $decodedToken->sub);
        $this->assertEquals($userRole, $decodedToken->role);
        $this->assertGreaterThan(time(), $decodedToken->exp);
    }
    
    #[Test]
    public function it_succeeds_with_a_valid_non_expired_access_token(): void {
        $userId = 'active-user-456';
        $userRole = 'user';
        $validToken = $this->generateValidAccessToken($userId, $userRole);
        $refreshToken = 'another-valid-refresh-token';
        $refreshTokenHash = password_hash($refreshToken, PASSWORD_DEFAULT);
        $_COOKIE[CookieConstants::REFRESH_TOKEN] = $refreshToken;
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Bearer ' . $validToken;
        $this->userModelMock->method('findForAuth')->with($userId)->willReturn([
            UserDataKeys::USER_ID => $userId,
            UserDataKeys::USER_ROLE => $userRole,
            UserDataKeys::REFRESH_TOKEN => $refreshTokenHash
        ]);

        sleep(1); // Wait 1 second to guarantee a new token timestamp

        $result = $this->authService->refreshAccessToken();
        $this->assertEquals(200, $result['status']);
        $this->assertArrayHasKey('accessToken', $result['data']);
        $newAccessToken = $result['data']['accessToken'];
        $this->assertNotEquals($validToken, $newAccessToken);
        $decodedToken = JWT::decode($newAccessToken, new Key($this->testSecretKey, JwtConstants::ALGO_HS256));
        $this->assertEquals($userId, $decodedToken->sub);
        $this->assertGreaterThan(time(), $decodedToken->exp);
    }

    // ==================================================================================
    // HELPER METHODS
    // ==================================================================================

    private function generateExpiredAccessToken(string $userId, string $role = 'user'): string {
        $payload = [
            JwtConstants::CLAIM_ISSUED_AT => time() - 7200,
            JwtConstants::CLAIM_EXPIRATION => time() - 3600,
            JwtConstants::CLAIM_SUBJECT => $userId,
            JwtConstants::CLAIM_ROLE => $role
        ];
        return JWT::encode($payload, $this->testSecretKey, JwtConstants::ALGO_HS256);
    }

    private function generateValidAccessToken(string $userId, string $role = 'user'): string  {
        $payload = [
            JwtConstants::CLAIM_ISSUED_AT => time(),
            JwtConstants::CLAIM_EXPIRATION => time() + 3600,
            JwtConstants::CLAIM_SUBJECT => $userId,
            JwtConstants::CLAIM_ROLE => $role
        ];
        return JWT::encode($payload, $this->testSecretKey, JwtConstants::ALGO_HS256);
    }
}