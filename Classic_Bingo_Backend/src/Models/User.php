<?php
namespace App\Models;
use PDO;

class User {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function findById(string $userId) {
        $stmt = $this->db->prepare("SELECT user_id, user_name, role FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    public function findByUsername(string $username) {
        $stmt = $this->db->prepare("SELECT user_id FROM users WHERE user_name = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    public function create(string $userId, string $username, string $avatarId): ?array {
        $sql = "INSERT INTO users (user_id, user_name, avatar_id) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute([$userId, $username, $avatarId])) {
            return $this->findById($userId);
        }
        return null;
    }
}