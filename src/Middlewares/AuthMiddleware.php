<?php

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\JWTService;

class AuthMiddleware
{
    // Request-scoped cache holding the authenticated user
    private static ?array $currentUser = null;

    // Intercept and authenticate the incomming http request.
    // called automatically by Router:: dispatch().
    public function handle(): void
    {

        // 1/ Extract Bearer token from HTTP.
        $token = Request::getBearerToken();

        if (!$token) {
            Response::error('Unautorized: Bearer token is missing', 401);
        }

        // 2. Cryptographic validate signature and expiration timestamp
        $payload = JWTService::validate($token);
        if (!$payload) {
            Response::error('Unauthorized: Invalid or expired token.', 401);
        }

        // 3. Extract user ID sealed inside the JWT and look up in MySQL
        $userId = (int) ($payload['sub'] ?? 0);
        $user = User::findById($userId);
        if (!$user) {
            Response::error('Unauthorized: User account not found or deactivated.', 401);
        }

        // 4. store authenticated user record in memory for this request
        self::$currentUser = $user;
    }

    // Retrive the currently authenticated user record.
    // return user array, or null if no user authenticated
    public static function user(): ?array
    {
        return self::$currentUser;
    }

    // Quick shortcut: retrives only the authenticated user's primary key ID.
    public static function id(): ?int
    {
        return self::$currentUser['id'] ?? null;
    }
}
