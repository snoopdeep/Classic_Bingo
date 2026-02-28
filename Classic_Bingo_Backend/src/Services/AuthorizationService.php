<?php 

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AuthorizationService 
{
    private string $secretKey;
    private int $expiration;

    public function __construct() 
    {
        $jwtConfig = require __DIR__ . '/../Config/jwt.php';
        $this->secretKey = $jwtConfig['secret'];
        $this->expiration = $jwtConfig['expiration'];
    }

    /**
     * Generate JWT token for user
     */
    public function generateToken(string $userId, string $role = 'user'): string 
    {
        $payload = [
            'iat' => time(),
            'exp' => time() + $this->expiration,
            'sub' => $userId, // Subject (user ID)
            'role' => $role
        ];

        return JWT::encode($payload, $this->secretKey, 'HS256');
    }

    /**
     * Validate and decode JWT token
     */
    public function validateToken(string $token): ?array 
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract user ID from token
     */
    public function extractUserIdFromRequest(): ?string 
    {
        if (!isset($_COOKIE['accessToken'])) {
            return null;
        }

        $tokenData = $this->validateToken($_COOKIE['accessToken']);
        return $tokenData['sub'] ?? null;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(?array $tokenData): bool 
    {
        return isset($tokenData['role']) && $tokenData['role'] === 'admin';
    }

    /**
     * Check if user owns the resource
     */
    public function isOwner(?array $tokenData, string $resourceUserId): bool 
    {
        return isset($tokenData['sub']) && $tokenData['sub'] === $resourceUserId;
    }
}