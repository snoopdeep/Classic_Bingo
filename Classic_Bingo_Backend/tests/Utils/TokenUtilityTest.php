<?php

namespace App\Tests\Utils;

use App\Constants\CookieConstants;
use App\Constants\JwtConstants;
use App\Constants\ServerKeys;
use App\Utils\TokenUtility;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for the TokenUtility class.
 *
 * @coversDefaultClass \App\Utils\TokenUtility
 */
final class TokenUtilityTest extends TestCase {
    /**
     * This trait enables the mocking of global PHP functions like setcookie().
     */
    use PHPMock;

    private string $testSecretKey = 'a-very-secret-key-for-testing';

    /**
     * Clears superglobal arrays before each test to ensure a clean state.
     */
    protected function setUp(): void {
        $_SERVER = [];
        $_COOKIE = [];
    }

    protected function tearDown(): void {
        $_SERVER = [];
        $_COOKIE = [];
    }

    // ===================================================================
    // Tests for generateAccessToken()
    // ===================================================================

    #[Test]
    public function it_generates_a_valid_and_decodable_jwt(): void  {
        // Arrange
        $userId = 'user-123';
        $role = 'admin';
        $expiration = 3600; // 1 hour

        // Act
        $token = TokenUtility::generateAccessToken($userId, $role, $this->testSecretKey, $expiration);

        // Assert
        $this->assertIsString($token);

        // Decode the token to verify its contents and signature
        $decoded = JWT::decode($token, new Key($this->testSecretKey, JwtConstants::ALGO_HS256));

        $this->assertInstanceOf(\stdClass::class, $decoded);
        $this->assertEquals($userId, $decoded->sub);
        $this->assertEquals($role, $decoded->role);
        $this->assertGreaterThan(time(), $decoded->exp);
        $this->assertLessThanOrEqual(time() + $expiration, $decoded->exp);
        $this->assertLessThanOrEqual(time(), $decoded->iat);
    }

    // ===================================================================
    // Tests for getAccessToken()
    // ===================================================================

    #[Test]
    public function it_extracts_token_from_valid_bearer_header(): void {
        // Arrange
        $token = 'my.jwt.token';
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Bearer ' . $token;

        // Act & Assert
        $this->assertEquals($token, TokenUtility::getAccessToken());
    }

    #[Test]
    public function it_handles_extra_whitespace_in_bearer_header(): void {
        // Arrange
        $token = 'my.jwt.token.with.space';
        // Note the multiple spaces after "Bearer"
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Bearer   ' . $token;

        // Act & Assert
        $this->assertEquals($token, TokenUtility::getAccessToken());
    }

    #[Test]
    public function it_returns_null_if_authorization_header_is_missing(): void {
        // Act & Assert
        $this->assertNull(TokenUtility::getAccessToken());
    }

    #[Test]
    public function it_returns_null_for_malformed_header_without_bearer_scheme(): void {
        // Arrange
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'my.jwt.token';

        // Act & Assert
        $this->assertNull(TokenUtility::getAccessToken());
    }

    #[Test]
    public function it_returns_null_for_incorrect_scheme(): void  {
        // Arrange
        $_SERVER[ServerKeys::HTTP_AUTHORIZATION] = 'Basic my.jwt.token';

        // Act & Assert
        $this->assertNull(TokenUtility::getAccessToken());
    }

    // ===================================================================
    // Tests for validateToken()
    // ===================================================================

    #[Test]
    public function it_successfully_validates_a_correct_token(): void {
        // Arrange
        $userId = 'user-456';
        $token = TokenUtility::generateAccessToken($userId, 'user', $this->testSecretKey, 60);

        // Act
        $decoded = TokenUtility::validateToken($token, $this->testSecretKey);

        // Assert
        $this->assertInstanceOf(\stdClass::class, $decoded);
        $this->assertEquals($userId, $decoded->sub);
    }

    #[Test]
    public function it_throws_expired_exception_for_expired_token(): void {
        // Arrange: Generate a token that expired 60 seconds ago
        $expiredToken = TokenUtility::generateAccessToken('user-789', 'user', $this->testSecretKey, -60);

        // Assert: Expect the specific exception to be thrown
        $this->expectException(ExpiredException::class);

        // Act
        TokenUtility::validateToken($expiredToken, $this->testSecretKey);
    }

    #[Test]
    public function it_returns_null_for_token_with_invalid_signature(): void {
        // Arrange
        $token = TokenUtility::generateAccessToken('user-abc', 'user', $this->testSecretKey, 60);
        $wrongSecret = 'this-is-the-wrong-key';

        // Act
        $result = TokenUtility::validateToken($token, $wrongSecret);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_malformed_token_string(): void {
        // Arrange
        $malformedToken = 'this.is.not.a.jwt';

        // Act
        $result = TokenUtility::validateToken($malformedToken, $this->testSecretKey);

        // Assert
        $this->assertNull($result);
    }


    // ===================================================================
    // Tests for getRefreshToken()
    // ===================================================================

    #[Test]
    public function it_gets_refresh_token_when_cookie_is_set(): void {
        // Arrange
        $refreshToken = 'a-valid-refresh-token';
        $_COOKIE[CookieConstants::REFRESH_TOKEN] = $refreshToken;

        // Act & Assert
        $this->assertEquals($refreshToken, TokenUtility::getRefreshToken());
    }

    #[Test]
    public function it_returns_null_when_refresh_token_cookie_is_not_set(): void {
        // Act & Assert
        $this->assertNull(TokenUtility::getRefreshToken());
    }

    // ===================================================================
    // Tests for cookie setting/clearing (using mocks)
    // ===================================================================

    #[Test]
    public function it_sets_refresh_token_cookie_with_correct_parameters(): void {
        // Arrange
        $token = 'my-refresh-token-123';
        $expiration = 86400; // 1 day
        $namespace = 'App\Utils';

        // Create a mock for the global setcookie() function within the target namespace
        $setcookie = $this->getFunctionMock($namespace, 'setcookie');

        // Assert: Expect setcookie to be called exactly once with specific arguments
        $setcookie->expects($this->once())
            ->with(
                $this->equalTo(CookieConstants::REFRESH_TOKEN),
                $this->equalTo($token),
                // Use a callback to inspect the options array
                $this->callback(function ($options) use ($expiration) {
                    $this->assertIsArray($options);
                    $this->assertTrue($options[CookieConstants::OPTION_HTTP_ONLY]);
                    $this->assertEquals(CookieConstants::VALUE_SAMESITE_STRICT, $options[CookieConstants::OPTION_SAME_SITE]);
                    $this->assertGreaterThan(time() + $expiration - 5, $options[CookieConstants::OPTION_EXPIRES]);
                    $this->assertLessThanOrEqual(time() + $expiration, $options[CookieConstants::OPTION_EXPIRES]);

                    // This callback must return true for the assertion to pass
                    return true;
                })
            );

        // Act
        TokenUtility::setRefreshTokenCookie($token, $expiration);
    }

    #[Test]
    public function it_clears_refresh_token_cookie_by_setting_past_expiration(): void  {
        // Arrange
        $namespace = 'App\Utils';

        // Mock the global setcookie() function
        $setcookie = $this->getFunctionMock($namespace, 'setcookie');

        // Assert: Expect setcookie to be called once with clearing parameters
        $setcookie->expects($this->once())
            ->with(
                $this->equalTo(CookieConstants::REFRESH_TOKEN),
                $this->equalTo(''), // The value should be an empty string
                // Use a callback to verify the expiration is in the past
                $this->callback(function ($options) {
                    $this->assertIsArray($options);
                    $this->assertLessThan(time(), $options[CookieConstants::OPTION_EXPIRES]);
                    return true;
                })
            );

        // Act
        TokenUtility::clearRefreshTokenCookie();
    }



}