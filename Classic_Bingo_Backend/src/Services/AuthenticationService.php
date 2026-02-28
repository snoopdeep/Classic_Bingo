<?php
namespace App\Services;

use App\Models\User;
use App\Utils\Database;
use App\Utils\UUIDGenerator; // Updated import

class AuthenticationService 
{
    private User $userModel;

    public function __construct() 
    {
        $db = Database::getInstance()->getConnection();
        $this->userModel = new User($db);
    }

    /**
     * Create a new guest user
     */
    public function createUser(string $username, string $avatarId): ?array 
    {
        // Check if username already exists
        if ($this->userModel->findByUsername($username)) {
            throw new \Exception('Username is already taken', 409);
        }

        // Generate UUID and create user
        $userId = UUIDGenerator::generate(); // Updated method call
        return $this->userModel->create($userId, $username, $avatarId);
    }

    /**
     * Get user by ID
     */
    public function getUserById(string $userId): ?array 
    {
        return $this->userModel->findById($userId);
    }

    /**
     * Validate user ID format
     */
    public function isValidUserId(string $userId): bool 
    {
        return UUIDGenerator::isValid($userId);
    }
}