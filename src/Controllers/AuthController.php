<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\JWTService;

class AuthController
{
    // POST /api/v1/auth/register
    // Register a new User
    public function register(): void
    {
        $body = Request::getBody();

        // 1. Sanitize & extract inputs
        $fullName = trim($body['full_name'] ?? "");
        $email = strtolower(trim($body['email'] ?? ""));
        $password = $body['password'] ?? "";
        $phone = trim($body['phone'] ?? "");

        // 2. validate required inputs
        $errors = [];
        if (empty($fullName)) {
            $errors['full_name'] = 'Full name is required.';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be atleast 8 characters long.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Password must contain atleast one Uppercase letter (A-Z).';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors['password'] = 'Password must contain at least one lowercase letter (a-z).';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Password must contain at least one number (0-9).';
        } elseif (!preg_match('/[\W_]/', $password)) {
            $errors['password'] = 'Password must contain at least one special character (e.g. !@#$%).';
        }

        // phone number check validation.
        if (!empty($phone)) {
            if (strlen($phone) > 20) {
                $errors['phone'] = 'Phone number cannot exceed 20 characters.';
            } elseif (!preg_match('/^[0-9+\-\s()]+$/', $phone)) {
                $errors['phone'] = 'Please enter a valid phone number.';
            }
        }

        // Return HTTP 422 if validatation fails
        if (!empty($errors)) {
            Response::error('Validation failed', 422, $errors);
        }

        // 3. Prevent duplicate email registratins
        if (User::findByEmail($email)) {
            Response::error('An account with this email is alredy exists', 409);
        }

        // 4. Save a new user into MySQL
        $userId = User::create([
            'full_name' => $fullName,
            'email' => $email,
            'password' => $password,
            'phone' => $phone ?: null,
            'role' => 'user'
        ]);

        // Fetch fresh user record
        $user = User::findById($userId);

        // 6. Generate signed JWT token
        $token = JWTService::generate([
            'sub' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);

        // 7. Return HTTP 201 Created with user info and jwt token
        Response::success([
            'user' => $user,
            'token' => $token
        ], 'User registered successfully', 201);
    }

    // POST /api/v1/auth/login
    // Authenticate user credentials and return a signed JWT token
    public function login(): void
    {
        $body = Request::getBody();
        $email = strtolower(trim($body['email'] ?? ""));
        $password = $body['password'] ?? "";

        // 1. Basic validation\
        if (empty($email) || empty($password)) {
            Response::error('Email and password are required.', 400);
        }

        // 2. Fetch user from database
        $user = User::findByEmail($email);

        // 3. Verify user existence AND verify Bcrypt password hash
        // security Return generic error medssage to prevent account ennumeration attack
        if (!$user || !User::verifyPassword($password, $user['password_hash'])) {
            Response::error('Invalid email or password.', 401);
        }

        // 4. check it he user account is active
        if ((int) $user['is_active'] !== 1) {
            Response::error('Your account has been deactivated. Please contact support.', 403);
        }

        // 5. Never expose password_hash in response
        unset($user['password_hash']);

        // 6. Generate signed JWT token
        $token = JWTService::generate([
            'sub' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);

        // 7. Return HTTP 200 ok with user info and JWT
        Response::success([
            'user' => $user,
            'token' => $token,
        ], 'Login Successfully');
    }

    // GET /api/v1/auth/profile
    // return profile of currently authenticated user
    public function profile(): void
    {

        // 1. Extract Bearer token from HTTP autherization header
        $token = Request::getBearerToken();

        if (!$token) {
            Response::error('Authorization token is required.', 401);
        }

        // 2. Cryptographic validate signature and expiration
        $payload = JWTService::validate($token);

        if (!$payload) {
            Response::error('Invalid token or expired.', 401);
        }

        // 3. Extract the user ID selaed inside the JWT
        $userId = (int) ($payload['sub'] ?? 0);
        $user = User::findById($userId);
        if (!$user) {
            Response::error('User account not found or deactivated.', 404);
        }

        // 4. Return user profile safely
        Response::success($user, 'User profile retrieved succesfully');
    }
}
