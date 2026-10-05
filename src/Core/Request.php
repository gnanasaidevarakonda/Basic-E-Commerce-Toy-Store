<?php

namespace App\Core;


class Request
{
    # 1. Get the HTTP Method (GET, POST, PUT, DELETE, etc)
    public static function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    # 2. get the Clean URL path without query parameters (eg. "/api/v1/products")
    public static function getPath(): string
    {
        // Get the raw URI (eg. "/api/v1/products?category=lego")
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // strip off the query string (turns "/products?page=2" into "/products")
        $path = parse_url($uri, PHP_URL_PATH);

        // Remove any trailing slashes (turns "/products/" into "/products")
        $path = rtrim($path, '/');

        // If the path is empty, default to "/"
        return $path === "" ? '/' : $path;
    }

    # 3. Read the incoming JSON body sent by the user/frontend

    public static function getBody(): array
    {
        // Read the raw text stream coming from the HTTP request
        $rawInput = file_get_contents('php://input');

        // If no JSON was sent, return an empty array
        if (empty($rawInput)) {
            // Fallback to standard $_POST if an old_school form was used
            return !empty($_POST) ? $_POST : [];
        }

        //Decode the json text into a PHP associative array
        $data = json_decode($rawInput, true);

        // return the array, or empty array if JSON was invalid
        return is_array($data) ? $data : [];
    }

    # 4. Shortcut: Get one specific field from the JSON body

    public static function input(string $key, mixed $default = null): mixed
    {
        $body = self::getBody();
        return $body[$key] ?? $default;
    }

    # 5. get all URL query parameters
    public static function getQuery(): array
    {
        return $_GET ?? [];
    }

    # getQueryParams 
    public static function getQueryParams(): array
    {
        return self::getQuery();
    }

    # 6. Shortcut: Get one specific URL query parameter
    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    # 7. Get the JWT Secret Token from the Authorization Header
    public static function getBearerToken(): ?string
    {
        // 1. Direct check in $__SERVER
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

        // 2. Fallback for headers array
        if (!$authHeader) {
            $headers = self::getHeaders();
            $authHeader = $headers['Authorization'] ?? $headers['AUTHORIZATION']
                ?? $headers['authorization'] ?? null;
        }

        // If header looks like "Bearer ejyugdsh76yshg6...." extract the token part
        if ($authHeader && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    # 8. Helper: Get all incoming HTTP request headers
    public static function getHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders() ?: [];
        }

        // fallbacks for server where getallheaders() is missing
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }
}
