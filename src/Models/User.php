<?php

namespace App\Models;

use App\Core\Database;
//use PDO;

class User
{

    // 1. Find a user by Email address (used during sign-up).
    public static function findByEmail(string $email): ?array
    {
        $db = Database::getConnection();
        $sql = "SELECT id, full_name, email, password_hash, phone, role, is_active, created_at FROM users 
        WHERE email = :email LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([':email' => strtolower(trim($email))]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    // 2. Find a user by thier unique ID(Used by Auth Middleware)
    // Note: we inentionally don't select password_hash here for safety.
    public static function findById(int $id): ?array
    {
        $db = Database::getConnection();

        $sql = "SELECT id, full_name, email, phone, role, is_active, created_at FROM
        users WHERE id = :id AND is_active = 1 LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);

        $user = $stmt->fetch();
        return $user ?: null;
    }


    // 3. Create a new registered user in database.
    public static function create(array $data): int
    {
        $db = Database::getConnection();

        // Hash the password securely using Bcrypt(Never store plain text)
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        $sql = "INSERT INTO users(full_name, email, password_hash, phone, role, is_active) 
        VALUES(:full_name, :email, :password_hash, :phone, :role, 1)";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':full_name' => trim($data['full_name']),
            ':email' => strtolower(trim($data['email'])),
            ':password_hash' => $hashedPassword,
            ':phone' => !empty($data['phone']) ? trim($data['phone']) : null,
            ':role' => $data['role'] ?? 'user'
        ]);

        // return the newly created user's primary key ID
        return (int) $db->lastInsertId();
    }


    // 4. Verify a plain-text password against a stored Bcrypt hash
    public static function verifyPassword(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }
}
