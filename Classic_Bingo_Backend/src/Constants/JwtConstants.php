<?php
namespace App\Constants;

final class JwtConstants
{
    private function __construct() {}

    // Standard JWT Claims (from RFC 7519)
    public const CLAIM_ISSUED_AT = 'iat';
    public const CLAIM_EXPIRATION = 'exp';
    public const CLAIM_SUBJECT = 'sub';
    public const SECRET = 'secret';
    public const ACCESS_TOKEN_EXPIRATION = 'access_token_expiration';
    public const REFRESH_TOKEN_EXPIRATION = 'refresh_token_expiration';

    // Custom Claims
    public const CLAIM_ROLE = 'role';

    // Algorithms
    public const ALGO_HS256 = 'HS256';
}