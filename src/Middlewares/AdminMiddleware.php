<?php

namespace App\Middlewares;

use App\Core\Response;

class AdminMiddleware
{
    // INtercept the imcommig request and verify admin access
    // Note admin middle should always run after authmiddleware in route chain.

    public function handle(): void
    {
        // 1. Retrive the authenticated user cached by authMiddleware
        $user = AuthMiddleware::user();

        // 2. Safety check: ENsure the user is actually authenticated
        if (!$user) {
            Response::error('Unautorized: Authentication required.', 401);
        }

        // 3. RBAC check: Ensure the user's role is strictly 'admin'
        if (($user['role'] ?? "") !== 'admin') {
            Response::error('Forbidden: Adminstrative privilages required.', 403);
        }
    }
}
