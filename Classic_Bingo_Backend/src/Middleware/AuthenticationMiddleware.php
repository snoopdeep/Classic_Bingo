<?php

namespace App\Middleware;

use App\Handlers\AppException;
use App\Config\JwtConfig;
use App\Core\Request;
use App\Utils\TokenUtility;
use App\Enums\ErrorCode;
use Firebase\JWT\ExpiredException; 

/**
 * Handles user authentication by validating a JSON Web Token (JWT).
 *
 * It validates jwt accessToken signature and expiration that is saved in http X-Signature Header.
 * and attaches the decoded user data to the Request object for later use.
 */

class AuthenticationMiddleware {

    /**
     * The secret key used to validate the JWT signature.
     * @var string
     */
    private string $jwtSecretKey;

    /**
     * Injects the JWT configuration to get the secret key.
     *
     * @param JwtConfig $jwtConfig The strongly-typed JWT configuration object.
     */
    public function __construct(JwtConfig $jwtConfig){
        $this->jwtSecretKey = $jwtConfig->secret; 
    }

    /**
     * Checks for and validates the JWT access token from the request.
     *
     * @param Request $request The application's request object.
     * @return void
     * @throws AppException If the token is missing, invalid, or expired.
     */
    public function handle(Request $request): void {   
       // 1. Retrieve the token
        $token = TokenUtility::getAccessToken();

        if($token === null){
            // No token was provided, so the user is not authenticated.
            throw new AppException(ErrorCode::AUTH_ACCESS_TOKEN_MISSING);
        }

        // 2. Validate the token's signature and expiration.
        $tokenData = null;

        try {
            // Attempt to validate the token
            $tokenData = TokenUtility::validateToken($token, $this->jwtSecretKey);
        } catch (ExpiredException $e) {
            // Token is expired. This is the refresh-friendly path.
            throw new AppException(ErrorCode::AUTH_ACCESS_TOKEN_EXPIRED, ['reason' => 'Token expired.']); 
        } catch (\Exception $e) {
            // Catch all other unexpected errors during decoding.
            $tokenData = null; 
        }

        if ($tokenData === null) {
            // CATCH 2: Token is malformed or has an invalid signature. This is the sign-in path.
            throw new AppException(ErrorCode::AUTH_ACCESS_TOKEN_INVALID);
        }

        // 3. Success: Attach the authenticated user's data (token payload) to the request object.
        $request->setAuthUser($tokenData); // [sub, role, exp, ... ]

    }
}