<?php

namespace App\Config;

use App\Constants\JwtConstants;
use Webmozart\Assert\Assert;

/**
 * A strongly-typed DTO for JSON Web Token (JWT) settings.
 *
 * This class validates and stores all JWT-related configuration, such as
 * the signing secret and token expiration times.
 */
final class JwtConfig
{
    /**
     * @var string The secret key used to sign and verify JWTs.
     */
    public readonly string $secret;

    /**
     * @var int The lifespan of an access token in seconds.
     */
    public readonly int $accessTokenExpiration;

    /**
     * @var int The lifespan of a refresh token in seconds.
     */
    public readonly int $refreshTokenExpiration;

    /**
     * Validates and maps the raw JWT configuration array.
     *
     * @param array<string, mixed> $config The configuration array, typically from `Config::get('jwt')`.
     * @throws \InvalidArgumentException If validation fails.
     * @return void
     */
    public function __construct(array $config) {
        Assert::keyExists($config, JwtConstants::SECRET);
        Assert::notEmpty($config[JwtConstants::SECRET], 'JWT secret cannot be empty.');
        
        Assert::keyExists($config, JwtConstants::ACCESS_TOKEN_EXPIRATION);
        Assert::integerish($config[JwtConstants::ACCESS_TOKEN_EXPIRATION], 'JWT access token expiration must be an integer.');

        Assert::keyExists($config, JwtConstants::REFRESH_TOKEN_EXPIRATION);
        Assert::integerish($config[JwtConstants::REFRESH_TOKEN_EXPIRATION], 'JWT refresh token expiration must be an integer.');

        $this->secret = $config[JwtConstants::SECRET];
        $this->accessTokenExpiration = (int) $config[JwtConstants::ACCESS_TOKEN_EXPIRATION];
        $this->refreshTokenExpiration = (int) $config[JwtConstants::REFRESH_TOKEN_EXPIRATION];
    }
}