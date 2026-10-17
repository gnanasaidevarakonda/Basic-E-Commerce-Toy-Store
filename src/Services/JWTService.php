<?php

namespace App\Services;

class JWTService
{

    // 1. Generate a signed JWT token for a logged-in user
    public static function generate(array $payload, int $expirySeconds = 86400): string
    {

        // Get the secret key from env or use a secure fallback
        $secretKey = getenv('JWT_SECRET') ?: 'toystore_super_secret_jwt_key!';

        // 1. Header: Specifies the algorithm (HMAC SHA-256) and type
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];

        // 2. Payload: Attach issued-at and expiration timestamps
        $payload['iat'] = time();
        $payload['exp'] = time() + $expirySeconds;

        // Base64URL encode for Header & Payload
        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));

        // 3. Signature: Cryptographgic sign the header + payload using secret key
        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $secretKey, true);
        $base64Signature = self::base64UrlEncode($signature);

        // Glue the 3 parts togther with dots
        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }


    // 2. Validate a JWT token and extract the user data inside it
    public static function validate(string $token): ?array
    {
        $secretKey = getenv('JWT_SECRET') ?: 'toystore_super_secret_jwt_key!';

        // A valid JWT must has 3 parts seperated by dots
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$base64Header, $base64Payload, $base64Signature] = $parts;

        // verify the cryptogphic sugnature
        $expectedSignature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $secretKey, true);
        $expectedBase64Signature = self::base64UrlEncode($expectedSignature);

        // Timing-safe comaparision to prevent timing attacks
        if (!hash_equals($expectedBase64Signature, $base64Signature)) {
            return null;
        }

        // Decode the payload back into a PHP array
        $payload = json_decode(self::base64UrlDecode($base64Payload), true);

        if (!is_array($payload)) {
            return null;
        }

        // check if token is expired
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }


    // Helper: Base64Url Encoding (safe for http header and urls)
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // Helper base64Url Decoding
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
