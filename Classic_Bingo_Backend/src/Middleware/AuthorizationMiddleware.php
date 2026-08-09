<?php

namespace App\Middleware;

use App\Handlers\AppException;
use App\Services\AuthorizationService;
use App\Core\Request;
use App\Enums\UserRole;
use App\Enums\ErrorCode;
use InvalidArgumentException; 

/**
 * Handles role-based authorization for authenticated users.
 */
class AuthorizationMiddleware {

    private const ROUTE_PARAM_USER_ID = 'userId';

    /**
     * The service used to check user roles and ownership.
     * @var AuthorizationService
     */
    private AuthorizationService $authzService;

    /**
     * Injects the AuthorizationService.
     *
     * @param AuthorizationService $authzService The service for handling authorization logic.
     */
    public function __construct(AuthorizationService $authzService) {
        $this->authzService = $authzService;
    }

    /**
     * Checks if the authenticated user has one of the required roles.
     *
     * @param Request $request The application's request object.
     * @param string  ...$requiredRoles A variable number of required role strings (e.g., 'owner', 'admin').
     * @return void
     * @throws AppException If the user is not authenticated (a server misconfiguration).
     */
    public function handle(Request $request, string ...$requiredRoles): void {

        // --- VALIDATION STEP ---
        $validRoles = UserRole::values();
        foreach ($requiredRoles as $role) {
        // The special 'owner' role is valid, but all others must exist in the UserRole enum.
            if ($role !== UserRole::OWNER->value && !in_array($role, $validRoles)) {
                // Throwing a standard exception will trigger the 500 error handler.
                throw new InvalidArgumentException("Invalid role '{$role}' provided in route definition.");
            }
        }
        // 1. Get the user data that was attached by the AuthenticationMiddleware.
        $tokenData = $request->getAuthUser();

        $isAuthorized = false; // assumes the user is not authorized by default.

        // 2. Check if the user meets at least one of the required roles.
        foreach ($requiredRoles as $role) {
            if ($role === UserRole::OWNER->value) {
                // Special 'owner' role: check if the user's ID from the token matches the user ID from the URL path.
                $routeParams = $request->getRouteParams(); 
                
                $targetUserId = $routeParams[self::ROUTE_PARAM_USER_ID] ?? null;

                if ($targetUserId && $this->authzService->isOwner($tokenData, $targetUserId)) {
                    $isAuthorized = true;
                    break; // User is the owner, no need to check other roles.
                }
            } else {
                // Check for a static role like 'admin'
                if ($this->authzService->hasRole($tokenData, $role)) {
                    $isAuthorized = true;
                    break; // User has the required role, no need to check others.
                }
            }
        }

        // 3. If after all checks the user is still not authorized, deny access.
        if (!$isAuthorized) {
            throw new AppException(ErrorCode::AUTH_FORBIDDEN);
        }

        // If authorized, the method completes and allows the request to proceed.
    }
}