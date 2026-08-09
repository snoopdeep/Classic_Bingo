<?php 

namespace App\Services;

use stdClass;

/**
 * Provides the core logic for performing authorization checks.
 *
 */
class AuthorizationService {

    /**
     * Checks if the user's decoded token data contains a specific role.
     *
     * @param ?stdClass $tokenData The decoded payload from the JWT, typically as an stdClass object.
     * @param string    $role      The role to check for (e.g., 'admin').
     * @return bool True if the user has the specified role, false otherwise.
     */
    public function hasRole(?stdClass $tokenData, string $role): bool {
        return isset($tokenData->role) && $tokenData->role === $role;
    }

    /**
     * Checks if the user's ID from the token matches the ID of a given resource.
     *
     * This is used for dynamic ownership checks, for example, to verify if a user is trying to access their own profile.
     *
     * @param ?stdClass $tokenData      The decoded payload from the JWT. The user's ID is expected in the 'sub' (subject) claim.
     * @param string    $resourceUserId The unique ID of the resource being accessed (e.g., a user ID from the URL).
     * @return bool True if the user's ID from the token matches the resource ID, false otherwise.
     */
    public function isOwner(?stdClass $tokenData, string|int $resourceUserId): bool {
        return isset($tokenData->sub) && $tokenData->sub === $resourceUserId;
    }
}