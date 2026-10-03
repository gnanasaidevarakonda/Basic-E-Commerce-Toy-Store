<?php

namespace App\Core;

class Response
{
    // Send a standardized JSON/HTTP response to browser or mobile app

    public static function json(mixed $data = null, int $statusCode = 200, string $message = ""): void
    {
        // 1. Set thye official http status code (eg. 200, 201, 500)
        http_response_code($statusCode);

        // 2. Tell the browser what we will sending will be JSON not HTML
        header('Content-Type:application/json; charset:utf-8');

        // 3. Determine if this response is success or not?
        $isSuccess = ($statusCode >= 200 && $statusCode <= 300);

        // 4. Build the standardized response box
        $payload = [
            'success' => $isSuccess,
            'status_code' => $statusCode
        ];

        // 5. If message was not provided add it to payload.
        if (!$message) {
            $payload['message'] = $message;
        }

        // 6. If data was provided add it too.
        if ($data !== null) {
            $payload['data'] = $data;
        }

        //7. convert the php array into JSON text and print it.
        // then finally stop all further PHP execution immediately

        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Quick shortcut for succesful operations.

    public static function succes(mixed $data = null, string $message = 'Success', int $statusCode = 200): void
    {
        self::json($data, $statusCode, $message);
    }

    // Quick shortcut for error operations.

    public static function error(string $message, int $statusCode = 400, mixed $errors = null): void
    {
        $data = ($errors !== null) ? ['errors' => $errors] : null;
        self::json($data, $statusCode, $message);
    }
}
