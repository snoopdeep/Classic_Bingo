<?php 

namespace App\Controllers;

use App\Constants\GameEntitiesConstants;
use App\Core\Request;
use App\Services\AuthenticationService;
use App\Utils\Logger;
use App\Constants\UserDataKeys;

/**
 * AuthController - Handles HTTP layer concerns for authentication endpoints.
 * Maps incoming HTTP requests to corresponding methods in the AuthenticationService.
 */

class AuthController  {

    // Define the public "menu" of handler methods as constants
    public const METHOD_SIGN_UP = 'signUp';
    public const METHOD_LOGIN = 'logIn';
    public const METHOD_REFRESH = 'refresh';
    public const METHOD_LOGOUT = 'logOut';
    public const METHOD_GET_ME = 'getMe';
    public const METHOD_GET_USER = 'getUser';

    /**
     * @var AuthenticationService The service layer responsible for business logic.
     */
    private AuthenticationService $authService;

    // Route parameter constants
    /**
     * @var string The key for the user ID parameter extracted from the route path.
     */
    public const ROUTE_PARAM_USER_ID = 'userId';

    /**
     * Inject the authentication service dependency.
     *
     * @param AuthenticationService $authService The authentication service instance.
     */
    public function __construct(AuthenticationService $authService) {
        $this->authService = $authService;
    }
    
    /**
     * Handles the user signup request (e.g., guest user creation).
     *
     * @param Request $request The request object containing the user data in the body.
     * @return array{data: mixed, status: int} A structured array for the response handler.
     */
    public function signUp(Request $request): array{
        Logger::info('signUp Request :: req.body is :: ', [$request->getBody()]);
       return $this->authService->signUpGuestUser($request->getBody());
    }

    /**
     * Handles the user login request (e.g., retrieving tokens for an existing guest ID).
     *
     * @param Request $request The request object containing the user ID in the body.
     * @return array{data: mixed, status: int} A structured array for the response handler.
     */
    public function logIn(Request $request): array{
        // get the userId from the req.body
        $userId = $request->getBody()[UserDataKeys::USER_ID];
       return $this->authService->loginGuestUser($userId);
    }

    /**
     * Handles the request to refresh the access token using a valid refresh token.
     *
     * @param Request $request The request object.
     * @return array{data: mixed, status: int} A structured array containing new tokens.
     */
    public function refresh(Request $request): array{
       return $this->authService->refreshAccessToken();
    }

    /**
     * Handles the user logout request, invalidating the refresh token.
     *
     * @param Request $request The request object, used to extract the authenticated user's ID.
     * @return array{data: mixed, status: int} A structured array indicating successful logout.
     */
    public function logOut(Request $request): array{
        // Extract userId from the authenticated user's token payload
        /** @var string $userId */
        $userId = $request->getAuthUser()->sub;
       return $this->authService->logOut($userId);
    }

    /**
     * Retrieves another user's profile information by ID.
     *
     * @param Request $request The request object containing the route parameters.
     * @param string $userId The user ID extracted from the route (e.g., /users/{userId}).
     * @return array{data: mixed, status: int} A structured array containing the user profile data.
     */
    public function getUser(Request $request): array {
       // Extract the target user ID from URL parameters
        $targetUserId = $request->getRouteParam(self::ROUTE_PARAM_USER_ID);

        // Pass the ID to the service
        $userProfile = $this->authService->getProfileById($targetUserId);

        return [
            GameEntitiesConstants::STATUS => 200,
            GameEntitiesConstants::DATA =>[
                $userProfile,
            ]
        ];
    }
}