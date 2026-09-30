<?php

// Tell the browser or client that we are sending JSON data
header('Content-Type: application/json; charset=utf-8');

// Return a clean status message
echo json_encode([
    'status' => 'success',
    'message' => 'E-Bike Backend Docker is up and running!',
    'environment' => [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Nginx',
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ]
], JSON_PRETTY_PRINT);