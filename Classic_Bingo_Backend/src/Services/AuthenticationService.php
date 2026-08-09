<?php

namespace App\Services;

use App\Models\User;
use App\Validators\AuthValidator;
use App\Utils\UUIDGenerator;
use App\Utils\TokenUtility;
use App\Utils\Logger;
use App\Enums\Avatar;
use App\Enums\ErrorCode;
use App\Constants\ErrorResponseKeys;
use App\Constants\UserDataKeys;
use App\Constants\GameEntitiesConstants;
use App\Constants\UserTableKeys;
use App\Constants\UserProfileKeys;
use App\Config\JwtConfig;
use App\Handlers\AppException;
use Exception;

/**
 * Handles the business logic for the entire user authentication lifecycle.
 * including signup, login, token management, and profile retrieval.
 */
class AuthenticationService {
    /**
     * @var User The data mapper for the 'users' table.
     */
    private User $userModel;

    /**
     * @var JwtConfig The strongly-typed DTO for JWT configuration.
     */
    private JwtConfig $jwtConfig;

    /**
     * Injects the required dependencies for the service.
     *
     * @param User $userModel The data mapper for interacting with user data.
     * @param JwtConfig $jwtConfig The DTO containing JWT settings like secret and expiration times.
     */
    public function __construct(User $userModel, JwtConfig $jwtConfig) {
        $this->userModel = $userModel;
        $this->jwtConfig = $jwtConfig;
    }

     /**
     * Registers a new guest user, generates tokens, and sets the refresh token cookie.
     *
     * @param array<string, mixed> $data Associative array containing 'user_name' and 'avatar_id'.
     * @return array{data: array<string, mixed>, status: int} The structured API response array.
     * @throws AppException For validation/business rule violations   
     * @throws Exception If an unexpected error occurs during database interaction.  
     */
    public function signUpGuestUser(array $data): array {
        
        // Step 1: Input validation (format/presence checks)
        $validatedData = AuthValidator::sanitizeAndValidateSignUpInput($data);

        // Step 2: Business rule validation - Avatar enum check
        if (Avatar::tryFrom($validatedData[UserDataKeys::AVATAR_ID]) === null) {
            throw new AppException(ErrorCode::VALIDATION_AVATAR_INVALID);
        }

        Logger::info("Request For signUp new user, validation and sanitization done",
        [
            UserDataKeys::USER_NAME => $validatedData[UserDataKeys::USER_NAME],
            UserDataKeys::AVATAR_ID => $validatedData[UserDataKeys::AVATAR_ID],
        ]);

        // Step 3: Business rule - Uniqueness check
        if ($this->userModel->findByUsername($validatedData[UserDataKeys::USER_NAME])) {
            throw new AppException(ErrorCode::AUTH_USERNAME_TAKEN);
        }

        // Step 4:User and Token Creation 
        $userId = UUIDGenerator::generate();
        $refreshToken = UUIDGenerator::generate();
        // Securely hash the refresh token before storing it in the database.
        $hashedRefreshToken = password_hash($refreshToken, PASSWORD_DEFAULT);

        try{
            /** @var array $newUser */
            $newUser = $this->userModel->create(
                $userId,
                $validatedData[UserDataKeys::USER_NAME], 
                $validatedData[UserDataKeys::AVATAR_ID],
                $hashedRefreshToken);
            } catch (Exception $e){
                // Catch unexpected exceptions, log them, and wrap
                    Logger::error('Unexpected error during user signup', [
                        UserDataKeys::USER_NAME => $validatedData[UserDataKeys::USER_NAME],
                        ErrorResponseKeys::ERROR => $e->getMessage(),
                        ErrorResponseKeys::TRACE => $e->getTraceAsString()
                    ]);
                    throw new AppException(ErrorCode::INFRA_UNEXPECTED_ERROR, [], $e);
            }

        // Step 5: Generate the short-lived access token.
        $accessToken = TokenUtility::generateAccessToken(
            $newUser[UserTableKeys::ID],
            $newUser[UserTableKeys::ROLE], 
            $this->jwtConfig->secret, 
            $this->jwtConfig->accessTokenExpiration);

        // Step 6: Send the raw (un-hashed) refresh token to the client in a secure cookie.
        TokenUtility::setRefreshTokenCookie($refreshToken, $this->jwtConfig->refreshTokenExpiration);

         Logger::info("New User Created",
        [
            UserDataKeys::USER_NAME => $newUser[UserTableKeys::NAME],
            UserDataKeys::AVATAR_ID => $newUser[UserTableKeys::AVATAR_ID],
        ]);

        // Step 8: Return response
        return [
            GameEntitiesConstants::DATA => [
                UserDataKeys::ACCESS_TOKEN => $accessToken,
                GameEntitiesConstants::USER => [
                    UserDataKeys::USER_ID => $newUser[UserTableKeys::ID],
                    UserDataKeys::USER_NAME => $newUser[UserTableKeys::NAME],
                    UserDataKeys::AVATAR_ID => $newUser[UserTableKeys::AVATAR_ID],
                    UserDataKeys::CREATED_AT => time(),
                ]
            ], 
            GameEntitiesConstants::STATUS => 201
        ];
    }
    
    /**
     * Logs in an existing guest user by ID and issues new tokens.
     *
     * @param string $userId The unique identifier of the user to log in.
     * @return array{data: array<string, mixed>, status: int} The structured API response array.
     * @throws AppException For validation or business rule violations.
     */
    public function loginGuestUser(string $userId): array {
 
        // Step 1: Input validation
        AuthValidator::validateUserId($userId);
        $userId = AuthValidator::sanitizeUserId($userId);
 
        // Step 2: Business rule - User existence check
        /** @var array|false $user */
        $user = $this->userModel->findProfileById($userId); // [user_id, user_name, avatar_id, role, created_at ]

        Logger::info('Loggin Attempt :: user found... ', ['userProfile'=>$user]);
        if (!$user) {
            // Log failed login attempt
            Logger::warning('Login attempt for non-existent user', [
                UserDataKeys::USER_ID => $userId
            ]);
            throw new AppException(ErrorCode::RESOURCE_USER_NOT_FOUND);
        }
 
        // Step 3:  Issue a new refresh token on every login and update the database hash.
        $refreshToken = UUIDGenerator::generate();
        $hashedRefreshToken = password_hash($refreshToken, PASSWORD_DEFAULT);
        $this->userModel->updateRefreshToken($user[UserTableKeys::ID], $hashedRefreshToken);
 
        // Generate a new access token. 
        $accessToken = TokenUtility::generateAccessToken(
            $user[UserTableKeys::ID],
            $user[UserTableKeys::ROLE], 
            $this->jwtConfig->secret, 
            $this->jwtConfig->accessTokenExpiration);
 
        // Set the new refresh token in the client's cookie.
        TokenUtility::setRefreshTokenCookie(
            $refreshToken, 
        $this->jwtConfig->refreshTokenExpiration);
 
        // Step 5: Log successful login
        Logger::info('User login successful', [
            UserDataKeys::USER_ID => $user[UserTableKeys::ID],
            UserDataKeys::USER_ROLE => $user[UserTableKeys::ROLE],
        ]);
 
        // Step 6: Return response
        return [
            GameEntitiesConstants::DATA => [
                UserDataKeys::ACCESS_TOKEN => $accessToken,
            ],
            GameEntitiesConstants::STATUS => 200
        ];
    }

    /**
     * Fetches basic user profile information from the database.
     *
     * @param string $userId The ID of the user whose profile is requested.
     * @return array<string, mixed> The partial user profile information (e.g., username, avatarId).
     * @throws \RuntimeException If the user profile is not found (though expected to be handled by caller).
     */
   public function getUserProfileInfo(string $userId): array {
        /** @var array|false $result */
        $result = $this->userModel->findProfileById($userId);

        if (!$result) {
            // For internal use, throw runtime exception; public methods should use AppException.
            throw new \RuntimeException('User profile not found.');
        }

        return [
            UserDataKeys::USER_NAME => $result[UserTableKeys::NAME],
            UserDataKeys::AVATAR_ID => $result[UserTableKeys::AVATAR_ID],
            // 'role' and 'sub' (user_id) will be merged from the token payload in the controller
        ];
    }

  /**
     * Generates a new access token using a valid refresh token.
     *
     * @return array{data: array<string, mixed>, status: int} The structured API response array with the new access token.
     * @throws AppException For invalid, missing, or revoked tokens.
     */
    public function refreshAccessToken(): array {
        // Step 1: Extract tokens
        $refreshToken = TokenUtility::getRefreshToken();

        if (!$refreshToken) {
            Logger::warning(' Access Token refresh attempted without refresh token');
            throw new AppException(ErrorCode::AUTH_REFRESH_TOKEN_MISSING);
        }   
        
        // The expired access token is still needed to identify the user.
        $accessToken = TokenUtility::getAccessToken();
        if (!$accessToken) {
            Logger::warning('Access Token refresh attempted without access token');
            throw new AppException(ErrorCode::AUTH_ACCESS_TOKEN_MISSING);
        }

        $userId = null;

        // Step 2: Extract userId from potentially expired access token
        try {
            // Attempt to decode the token. This will only succeed if the token is fully valid.
            $decodedAccess = TokenUtility::validateToken($accessToken, $this->jwtConfig->secret);
            
            // Handle invalid (but not expired) tokens.
            // If validateToken returns null, it's because of a bad signature or malformed structure.
            if ($decodedAccess === null) {
                Logger::warning('Token refresh attempted with invalid token structure');
                throw new AppException(ErrorCode::AUTH_ACCESS_TOKEN_INVALID);
            }
            
            // If we're here, the token is valid and not expired (an edge case for refresh).
            $userId = $decodedAccess->sub;

        } catch (\Firebase\JWT\ExpiredException $e) {
            // Expected path: the token is expired but otherwise well-formed.
            $tokenParts = explode('.', $accessToken);
            if (count($tokenParts) === 3) {
                $payload = json_decode(base64_decode($tokenParts[1]));
                if (is_object($payload) && isset($payload->sub)) {
                    $userId = $payload->sub;
                }
            }
        }
        
        //Step 3: Final safeguard: If userId is still null after all checks, the token was unusable.
        if ($userId === null) {
            Logger::error('Failed to extract user ID from access token during refresh');
            throw new AppException(ErrorCode::AUTH_ACCESS_TOKEN_INVALID);
        }

        // Step 4: Fetch user accessToken and verify refresh token
        $user = $this->userModel->findForAuth($userId); // [refreshToken, role [to generate new accessToken]]

        // Security check: ensure user exists and the refresh token matches.
        if (!$user || !$user[UserTableKeys::REFRESH_TOKEN] || !password_verify($refreshToken, $user[UserTableKeys::REFRESH_TOKEN])) {
            Logger::warning('Token refresh failed: invalid or revoked refresh token', [
                UserDataKeys::USER_ID => $userId
            ]);
            throw new AppException(ErrorCode::AUTH_REFRESH_TOKEN_INVALID_OR_REVOKED);
        }

        //  Step 5: Issue a new access token.
        $newAccessToken = TokenUtility::generateAccessToken(
            $userId, 
            $user[UserTableKeys::ROLE], 
            $this->jwtConfig->secret, 
            $this->jwtConfig->accessTokenExpiration);
        
        // Step 6: Log successful refresh
        Logger::info('Access token refreshed successfully', [
            UserDataKeys::USER_ID => $userId
        ]);

        return [
            GameEntitiesConstants::DATA => [
                UserDataKeys::ACCESS_TOKEN => $newAccessToken
            ], 
            GameEntitiesConstants::STATUS => 200
        ];
    }

    /**
     * Log out a user out by invalidating their session.
     *
     * @param string $userId The ID of the user to log out (extracted from a valid access token).
     * @return array{data: array<string, int>, status: int} The structured API response array.
     * @throws AppException For validation errors.
     */
     public function logout(string $userId): array {

        // Step 1: Input validation and sanitization
        AuthValidator::validateUserId($userId);
        $userId = AuthValidator::sanitizeUserId($userId);
    
        // Step 2: Invalidate session
        // Remove the refresh token hash from the database. 
        $this->userModel->clearRefreshToken($userId);
        
        // Instruct the client's browser to clear the refresh token cookie.
        TokenUtility::clearRefreshTokenCookie();
        
        // Step 3: Log successful logout
        Logger::info('User logged out successfully', [
            UserDataKeys::USER_ID => $userId
        ]);        
        
        // Step 4: Return success response
        return [
            GameEntitiesConstants::DATA => [
                GameEntitiesConstants::MESSAGE => 1 // Send 1 (true) or 0 (false) status.
            ], 
            GameEntitiesConstants::STATUS => 200
        ];
    }

    /**
     * Retrieves the complete user profile including wallet and game stats.
     *
     * @param string $userId The ID of the user whose complete profile is requested.
     * @return array{data: array<string, mixed>, status: int} The structured API response array containing user profile and stats.
     * @throws AppException If the user resource is not found.
     */
    public function getProfileById(string $userId): array {
        // Step 1: Input validation
        AuthValidator::validateUserId($userId);
        $userId = AuthValidator::sanitizeUserId($userId);
        
        // Fetch complete profile with wallet and stats
        /** @var array|false $result */
        $result = $this->userModel->findCompleteProfileById($userId);
        
        if (!$result) {
            Logger::warning('Get profile attempt for non-existent user', [
                UserDataKeys::USER_ID => $userId
            ]);
            throw new AppException(ErrorCode::RESOURCE_USER_NOT_FOUND);
        }
        
        // Calculate total draws (games - wins - losses)
        $totalDraws = max(0, (int)$result['total_games'] - (int)$result['total_wins'] - (int)$result['total_losses']);
        
        return [
            GameEntitiesConstants::STATUS => 200, 
            GameEntitiesConstants::DATA => [
                // User Data
                UserDataKeys::USER_ID => $result[UserTableKeys::ID],
                UserDataKeys::USER_NAME => $result[UserTableKeys::NAME],
                UserDataKeys::AVATAR_ID => $result[UserTableKeys::AVATAR_ID],
                UserDataKeys::USER_ROLE => $result[UserTableKeys::ROLE],
                UserDataKeys::CREATED_AT => $result[UserTableKeys::CREATED_AT],

                // Wallet Data
                UserProfileKeys::BINGO_COINS_API => (int)$result[UserProfileKeys::BINGO_COINS],
                UserProfileKeys::DICE_API => (int)$result[UserProfileKeys::DICE],
                
                // Stats Data
                UserProfileKeys::TOTAL_GAMES_API => (int)$result[UserProfileKeys::TOTAL_GAMES],
                UserProfileKeys::TOTAL_WINS_API => (int)$result[UserProfileKeys::TOTAL_WINS],
                UserProfileKeys::TOTAL_LOSSES_API => (int)$result[UserProfileKeys::TOTAL_LOSSES],
                UserProfileKeys::TOTAL_DRAWS_API => $totalDraws,
                UserProfileKeys::CURRENT_WIN_STREAK_API => (int)$result[UserProfileKeys::CURRENT_WIN_STREAK],
                UserProfileKeys::BEST_WIN_STREAK_API => (int)$result[UserProfileKeys::BEST_WIN_STREAK]
            ]
        ];
    }

}