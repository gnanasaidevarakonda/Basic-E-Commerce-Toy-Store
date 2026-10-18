<?php

namespace App\Middlewares;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

use PDO;

class RateLimitMiddleware
{
    /**
     * Production Sliding WINDOW RATE LIMITER
     * Measure how many requests an IP sends over 60 seconds
     * Enforce strict limit on login/Register while allowing generous limit on public catelog browsing.
     */
    public function handle(): void
    {
        $db = Database::getConnection();

        // 1. Auto-healing table creation
        self::ensureTableExists($db);

        // 2. Resolve client IP across reverse SSH tunnels, Ngnix or cloudflare
        $clientIp = self::getClientIp();
        $path = Request::getPath();

        // 3. Dynamic Tier Assignment:
        // Sensitive authentication routes get strict throtling
        if (str_contains($path, '/auth/login') || str_contains($path, 'auth/register')) {
            $maxAtempts = 10;
            $windowSeconds = 60;
            $tierName = 'auth';
        } else {
            $maxAtempts = 60;
            $windowSeconds = 60;
            $tierName = 'api';
        }

        // Generate a unique cryptographic hash key per client
        $rateKey = hash('sha256', "{$clientIp}:{$tierName}");
        $now = time();


        // 4. Fetch the existing rate limit record from my-sql
        $stmt = $db->prepare("SELECT hits, reset_at FROM rate_limits WHERE rate_key = :key LIMIT 1");
        $stmt->execute([':key' => $rateKey]);
        $record = $stmt->fetch();

        // If no records exists or the 60- seconds has expired read counter to 1
        if (!$record || $now >= (int)$record['reset_at']) {
            $resetAt = $now + $windowSeconds;
            $upsert = $db->prepare("INSERT INTO rate_limits (rate_key, hits, reset_at) VALUES (:key, 1, :reset_at) 
            ON DUPLICATE KEY UPDATE hits =1, reset_at = :reset_at_update");
            $upsert->execute([
                ':key' => $rateKey,
                ':reset_at' => $resetAt,
                ':reset_at_update' => $resetAt
            ]);

            self::setHeaders($maxAtempts, $maxAtempts - 1, $resetAt - $now);
            return;
        }
        $hits = (int) $record['hits'];
        $resetAt = (int) $record['reset_at'];

        // 5. Check if client has exceeded thier allowed limit
        if ($hits >= $maxAtempts) {
            $retryAfter = max(1, $resetAt - $now);
            self::setHeaders($maxAtempts, 0, $retryAfter);
            header("Retry-After: {$retryAfter}");

            // Immediastly halt with HTTP 429 too many Requests
            Response::error("Rate limit exceeded. Too many requests. PLease wait {$retryAfter} seconds before trying again.", 429);
        }

        // 6. increment the counter for this active window
        $inc = $db->prepare("UPDATE rate_limits SET hits = hits+1 WHERE rate_key = :key");
        $inc->execute(['key' => $rateKey]);

        $remaining = max(0, $maxAtempts - ($hits + 1));
        self::setHeaders($maxAtempts, $remaining, $resetAt - $now);
    }

    /**
     * Accurately extracts the client's real IP address accross proxies and tunnels.
     */
    private static function getClientIp(): string
    {
        // Check Cloudflare or Reverse Proxy headers (location.run/Nginx)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return trim($_SERVER['HTTP_X_REAL_IP']);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Attach standard IETF Rate_lmit HTTP response headers so API clients know thier quota
     */
    private static function setHeaders(int $limit, int $remaining, int $retryAfter): void
    {
        header("X_RateLimit-Limit: {$limit}");
        header("X_RateLimit-Remaining: {$remaining}");
        header("X_RateLimit-Reset: {$retryAfter}");
    }

    /**
     * Create the iundexed rate_limits storage table automatically in MySQL
     */
    private static function ensureTableExists(PDO $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `rate_limits`(
        `rate_key` VARCHAR(191) PRIMARY KEY,
        `hits` INT NOT NULL DEFAULT 1,
        `reset_at` INT NOT NULL,
        INDEX `idx_reset_at`(`reset_at`)
        )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

        $db->exec($sql);
    }
}
