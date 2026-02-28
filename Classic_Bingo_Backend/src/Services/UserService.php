<?php 

namespace App\Services;

use App\Utils\Response;

class UserService 
{
    private AuthenticationService $authService;
    private AuthorizationService $authzService;

    public function __construct() 
    {
        $this->authService = new AuthenticationService();
        $this->authzService = new AuthorizationService();
    }


    // create new user and return with token

    public function registerUser(array $data): void 
    {
        try {
            // validate input
            if (empty($data['user_name']) || empty($data['avatar_id'])) {
                Response::json(['error' => 'Username and avatar_id are required'], 400);
            }

            // Validate avatar_id
            $validAvatars = ['avatar_01', 'avatar_02', 'avatar_03', 'avatar_04', 'avatar_05'];
            if (!in_array($data['avatar_id'], $validAvatars)) {
                Response::json(['error' => 'Invalid avatar_id'], 400);
            }

            // Create user
            $newUser = $this->authService->createUser($data['user_name'], $data['avatar_id']);
            
            if (!$newUser) {
                Response::json(['error' => 'Failed to create user'], 500);
            }

            // Generate token, using AuthenticationService();
            $token = $this->authzService->generateToken($newUser['user_id'], $newUser['role']);

            // Set secure cookie
            $this->setAuthCookie($token);

            Response::json([
                'message' => 'User created successfully',
                'user' => [
                    'user_id' => $newUser['user_id'],
                    'user_name' => $newUser['user_name'],
                    'role' => $newUser['role']
                ]
            ], 201);

        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

 
     // get current user profile

    public function getCurrentUserProfile(): void 
    {
       // extract user ID from token -> AuthorizationService()
        $userId = $this->authzService->extractUserIdFromRequest();
        
        if (!$userId) {
            Response::json(['error' => 'Authentication required'], 401);
        }

        // Get user data
        $user = $this->authService->getUserById($userId);
        
        if (!$user) {
            Response::json(['error' => 'User not found'], 404);
        }

        Response::json([
            'user_id' => $user['user_id'],
            'user_name' => $user['user_name'],
            'role' => $user['role']
        ]);
    }

    /**
     * Get any user profile (admin only or self)
     */
    public function getUserProfile(string $targetUserId): void 
    {
        // Extract current user from token
        $currentUserId = $this->authzService->extractUserIdFromRequest();
        
        if (!$currentUserId) {
            Response::json(['error' => 'Authentication required'], 401);
        }

        // get current user's token data for role checking
        $tokenData = null;
        if (isset($_COOKIE['accessToken'])) {
            $tokenData = $this->authzService->validateToken($_COOKIE['accessToken']);
        }

        // check authorization: must be admin OR accessing own profile
        $isAdmin = $this->authzService->isAdmin($tokenData);
        $isOwner = $this->authzService->isOwner($tokenData, $targetUserId);

        if (!$isAdmin && !$isOwner) {
            Response::json(['error' => 'Forbidden: You can only access your own profile'], 403);
        }

        // Get target user data
        $user = $this->authService->getUserById($targetUserId);
        
        if (!$user) {
            Response::json(['error' => 'User not found'], 404);
        }

        Response::json([
            'user_id' => $user['user_id'],
            'user_name' => $user['user_name'],
            'role' => $user['role']
        ]);
    }

    /**
     * Logout user
     */
    public function logout(): void 
    {
        // Clear the auth cookie
        setcookie('accessToken', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => false, // Set to true in production
            'samesite' => 'Strict'
        ]);

        Response::json(['message' => 'Logged out successfully']);
    }

    /**
     * Set authentication cookie
     */
    private function setAuthCookie(string $token): void 
    {
        setcookie('accessToken', $token, [
            'expires' => time() + (24 * 60 * 60), // 24 hours
            'path' => '/',
            'httponly' => true,
            'secure' => false, // Set to true in production with HTTPS
            'samesite' => 'Strict'
        ]);
    }
}

