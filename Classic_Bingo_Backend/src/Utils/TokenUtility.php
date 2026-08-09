<?php

namespace App\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;
use Exception;
use App\Constants\ServerKeys;
use App\Enums\AppEnvironment;
use App\Constants\CookieConstants;
use App\Constants\JwtConstants;
use Firebase\JWT\ExpiredException;

/**
 * A stateless utility class for handling JWT and cookie operations.
 *
 */
final class TokenUtility {
    /**
     * Private constructor to prevent creating an instance of this utility class.
     */
    private function __construct(){}

    /**
     * Generates a JWT access token.
     *
     * @param string $userId The user's unique identifier.
     * @param string $role The user's role.
     * @param string $secretKey The secret key for signing the token.
     * @param int $expiration The token's lifetime in seconds.
     * @return string The generated JWT.
     */
    public static function generateAccessToken(string $userId, string $role, string $secretKey, int $expiration): string {
        $payload = [
            JwtConstants::CLAIM_SUBJECT => $userId,
            JwtConstants::CLAIM_ROLE => $role,
            JwtConstants::CLAIM_EXPIRATION => time() + $expiration,
            JwtConstants::CLAIM_ISSUED_AT => time(),
        ];
        return JWT::encode($payload, $secretKey, JwtConstants::ALGO_HS256);
    }

    /**
     * Extracts the Bearer token from the 'Authorization' header.
     *
     * @return string|null The token string if found, otherwise null.
     */
    public static function getAccessToken(): ?string{
        $authHeader = $_SERVER[ServerKeys::HTTP_AUTHORIZATION] ?? null;
        $accessToken = null;
        if ($authHeader && preg_match('/^Bearer\s+(.*)$/', $authHeader, $matches)) {
            $accessToken = $matches[1];
        }
        return $accessToken;
    }

    /**
     * Validates a token's signature and expiration.
     *
     * @param string $token The JWT string to validate.
     * @param string $secretKey The secret key used for verification.
     * @return stdClass|null The decoded payload as an stdClass object on success, or null on any other failure.
     * @throws ExpiredException If the token is expired.
     */
    public static function validateToken(string $token, string $secretKey): ?stdClass{
        try {
            return JWT::decode($token, new Key($secretKey, JwtConstants::ALGO_HS256));
        } catch (ExpiredException $e) {
            // Re-throw the exception so the caller can handle token expiration specifically.
            throw $e;
        } catch (Exception $e) {
            // Any other exception (bad signature, etc.) means the token is truly invalid.
            return null;
        }
    }

    /**
     * Sets the secure, HTTP-only refresh token cookie.
     *
     * @param string $token The raw refresh token string.
     * @param int $expiration The cookie's lifetime in seconds.
     * @return void
     */

public static function setRefreshTokenCookie(string $token, int $expiration): void {
    
    // Determine the environment status
    $isDevelopment = ($_ENV['APP_ENV'] ?? AppEnvironment::PRODUCTION) === AppEnvironment::DEVELOPMENT;
    
    // Define the options array
    $options = [
        // ... (other options)
        CookieConstants::OPTION_EXPIRES => time() + $expiration,
        CookieConstants::OPTION_HTTP_ONLY => true,
        
        // 💡 FIX 1: Set Secure to false if we are in DEVELOPMENT
        CookieConstants::OPTION_SECURE => !$isDevelopment, 
        
        // 💡 FIX 2: Relax SameSite policy to LAX for local development compatibility
        CookieConstants::OPTION_SAME_SITE => CookieConstants::VALUE_SAMESITE_LAX,
        
        // Ensure path is root
        'path' => '/' 
    ];

    setcookie(CookieConstants::REFRESH_TOKEN, $token, $options);
}

    /**
     * Retrieves the refresh token from the request cookies.
     *
     * @return string|null The refresh token if it exists, otherwise null.
     */
    public static function getRefreshToken(): ?string{
        // DEBUG LOG: Log all cookies the server receives
        // Logger::info('Incoming Cookies during Refresh', $_COOKIE);
        return $_COOKIE[CookieConstants::REFRESH_TOKEN] ?? null;
    }

    /**
     * Instructs the browser to clear the refresh token cookie.
     *
     * @return void
     */

    public static function clearRefreshTokenCookie(): void{

    $isDevelopment = ($_ENV['APP_ENV'] ?? AppEnvironment::PRODUCTION) === AppEnvironment::DEVELOPMENT;

    setcookie(CookieConstants::REFRESH_TOKEN, '', [
        CookieConstants::OPTION_EXPIRES => time() - 3600,
        'path' => '/', 
        CookieConstants::OPTION_HTTP_ONLY => true,
        CookieConstants::OPTION_SECURE => !$isDevelopment,
        CookieConstants::OPTION_SAME_SITE => CookieConstants::VALUE_SAMESITE_LAX
    ]);
}

}
