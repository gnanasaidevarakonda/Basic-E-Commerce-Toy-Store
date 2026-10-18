<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Middlewares\AuthMiddleware;
use App\Models\User;
use App\Services\JWTService;

class AdminAuthController
{
    // POST /api/v1/admin/auth/register
    // backoffice staff onbording for managers
    // requires master key for unauthorized registration

    /*
    public function register(): void
    {
        $body = Request::getBody();

        // 1. sanitize & extract inputs
        $fullName = trim($body['full_name'] ?? '');
        $email = strtolower(trim($body['email'] ?? ''));
        $password = $body['password'] ?? '';
        $phone = trim($body['phone'] ?? '');
        $role = strtolower(trim($body['role'] ?? 'manager')); // Defaults to manager
        $adminSecret = trim($body['admin_secret'] ?? '');

        // 2. validate the input fileds
        $errors = [];
        if (empty($fullName)) {
            $errors['full_name'] = 'Full name is required';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email is required';
        }

        if (empty($password) || strlen($password) < 8) {
            $errors['password'] = 'Staff password must be at least 8 characters long';
        }

        // Validate that requested role is strictly 'manager' or 'admin'
        if (!in_array($role, ['manager', 'admin'], true)) {
            $errors['role'] =  "Role must be either 'manager' or 'admin'.";
        }

        // 3. Security Guard: Verify the master server secret
        // Reads from .env or uses our local development fallback
        $expectedSecret = getenv('ADMIN_REGISTRATION_SECRET') ?: 'admin_master_secret';

        if (empty($adminSecret) || !hash_equals($expectedSecret, $$adminSecret)) {
            Response::error('Forbidden: Invalid or missing adminstaor master secret key.', 403);
        }

        // Return HTTP 422 if basic validation fails
        if (!empty($errors)) {
            Response::error('Validation failed.', 422, $errors);
        }

        // 4. Prevent duplicate accounts with the same email
        if (User::findByEmail($email)) {
            Response::error('An account with this email already exists.', 409);
        }

        // 5. Save the new staff member into MYSQL
        $userId = User::create([
            'full_name' => $fullName,
            'email' => $email,
            'password' => $password,
            'phone' => !empty($phone) ? $phone : null,
            'role' => $role
        ]);

        // Fetch fresh user record
        $user = User::findById($userId);

        // 6. Gnenrate signes JWT token carying thier specific role
        $token = JWTService::generate([
            'sub' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);

        // 7. return HTTP 201 created
        Response::success([
            'user' => $user,
            'token' => $token
        ], ucfirst($role) . 'account registered succesfully.', 201);
    }

    */

    /* POST /api/v1/admin/auth/login
       Authenticate backoffice staff
       Allows both 'manger' & 'admin', while rejecting regular customer.
    */
    public function login(): void
    {
        $body = Request::getBody();
        $email = strtolower(trim($body['email'] ?? ''));
        $password = $body['password'] ?? '';

        // 1. basic validation
        if (empty($email) || empty($password)) {
            Response::error('Email and password are required.', 400);
        }

        // 2. fetch user by email
        $user = User::findByEmail($email);

        // 3. verify user existence os user email and Bcrypt pasword hash
        if (!$user || !User::verifyPassword($password, $user['password_hash'])) {
            Response::error('Invalid email or password.', 401);
        }

        // 4. check if account is active
        if ((int) $user['is_active'] !== 1) {
            Response::error('This account has been deactivated, Please Contact Adminstratation.', 403);
        }

        // 5. Critical RBAC Guard: Ensure user is either manager or admin
        // If standard user attempts to login here, reject them
        if (!in_array($user['role'], ['manager', 'admin'], true)) {
            Response::error('Forbidden: Access denied. Management previlages required.', 403);
        }

        // Presence Tracking: Mark staff user online in database.
        $db = Database::getConnection();
        $updateStmt = $db->prepare("UPDATE users SET is_online = 1, updated_at = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => (int) $user['id']]);

        // 6. Never expose password_hash in response
        unset($user['password_hash']);

        // 7. Generate signed JWT token with thier role sealed inside
        $token = JWTService::generate([
            'sub' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);

        // 8. Return HTTP 200 ok
        Response::success([
            'user' => $user,
            'token' => $token
        ], ucfirst($user['role']) . ' login successful.');
    }

    /**
     * POST /api/v1/admin/auth/logout
     * GUarded by authmiddleware: requires valid Bearer token.
     * sets is_online = 0 in database for the authentication staff user.
     */
    public function logout(): void
    {
        $user = AuthMiddleware::user();

        if ($user && isset($user['id'])) {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET is_online = 0 WHERE id = :id");
            $stmt->execute(['id' => (int) $user['id']]);
        }

        Response::success(null, 'Staff logged out successfully.');
    }

    /**
     * POST /api/v1/admin/auth/ping
     * Presence Heartbeat: keeps staff marked online and refreshes updated_at timnestamp.
     */
    public function ping(): void
    {
        $user = AuthMiddleware::user();

        if ($user && isset($user['id'])) {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET is_online =1, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => (int)$user['id']]);
        }

        Response::success(['is_online' => 1], 'Presence heartbeat acknowledged.');
    }
}
