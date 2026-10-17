<?php

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;

class SecurityMiddleware
{
    /**
     * ========================================================================
     * BOT & SCANNER DEFENSE + PRODUCTION SECURITY HEADERS
     * ========================================================================
     * Intercepts every incoming request to:
     * 1. Attach strict security headers to prevent Clickjacking & MIME-sniffing.
     * 2. Detect and drop automated attack scanners (sqlmap, nikto, dirbuster).
     * 3. Trap malicious path traversal probes (e.g. searching for .env or wp-admin).
     */
    public function handle(): void
    {
        // 1. Send Enterprise HTTP Security Headers
        self::setSecurityHeaders();

        $path      = strtolower(Request::getPath());
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $method    = Request::getMethod();

        // 2. Scanner Trap: Detect common malicious probing attempts
        $maliciousProbes = [
            '.env',
            '.git',
            'wp-admin',
            'wp-login',
            'phpinfo',
            'xmlrpc',
            'eval-stdin',
            'boaform',
            'admin.php',
            'config.php',
            'passwd'
        ];

        foreach ($maliciousProbes as $probe) {
            if (str_contains($path, $probe)) {
                // Drop scanning bots immediately
                Response::error('Security violation: Access denied by bot defense shield.', 403);
            }
        }

        // 3. Known Vulnerability Scanner Tools Check
        $badBots = [
            'sqlmap',
            'nikto',
            'masscan',
            'dirbuster',
            'gobuster',
            'wprecon',
            'nmap',
            'zgrab',
            'havij'
        ];

        foreach ($badBots as $bot) {
            if (str_contains($userAgent, $bot)) {
                Response::error('Security violation: Automated scanner tool detected.', 403);
            }
        }

        // 4. Block empty User-Agents on write operations (POST, PUT, PATCH, DELETE)
        // Legitimate browsers and mobile apps ALWAYS supply a User-Agent header
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && empty($userAgent)) {
            Response::error('Bad Request: Missing User-Agent header on state-modifying request.', 400);
        }
    }

    /**
     * Sets OWASP-recommended HTTP security headers on all responses.
     */
    private static function setSecurityHeaders(): void
    {
        // Prevent MIME-type sniffing (forces browser to honor Content-Type)
        header('X-Content-Type-Options: nosniff');

        // Prevent Clickjacking (blocks embedding this API in external malicious iframes)
        header('X-Frame-Options: SAMEORIGIN');

        // Legacy XSS filter protection
        header('X-XSS-Protection: 1; mode=block');

        // Control referrer information leakage
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Disable unused browser hardware APIs (microphone, camera, geolocation)
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
}
