<?php

namespace App\Middlewares;

use App\Core\Response;

class StaffMiddleware
{
    /**
     * Intercept the incomming request and verify backoffice staff access.
     * Grants access to both 'manager' & 'admin' roles.
     * 
     * Note: This middleware must run after AuthMiddleware in the route chain
     */
    public function handle(): void
    {
        // 1. Retrive the authenticated user cached in emmeory by Authmiddleware
        $user = AuthMiddleware::user();

        // 2. Safety check: Ensure user is authenticated
        if (!$user) {
            Response::error('Unauthorized: Authentication required.', 401);
        }

        // 3. RBAC check: Ensure the role is either manager or admin
        if (!in_array($user['role'] ?? '', ['manager', 'admin'], true)) {
            Response::error('Forbidden: Staff or management privilages required.', 403);
        }
    }
}
