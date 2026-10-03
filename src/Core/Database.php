<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    //Holds the single active connection instance

    private static ?PDO $connection = null;

    //Priavte constructor to prevents creating multiple objects with new Database()

    private function __construct() {}

    //Get the single active PDO database connecttion

    public static function getConnection(): PDO
    {
        // if we alredy connected earlier with this request, just return the same connection.

        if (self::$connection === null) {
            // Read credentials from docker environment or use defaults.

            $host = getenv('DB_HOST') ?: 'db';
            $port = getenv('DB_PORT') ?: '3306';
            $database = getenv('DB_DATABASE') ?: 'ebike_store';
            $username = getenv('DB_USERNAME') ?: 'ebike_user';
            $password = getenv('DB_PASSWORD') ?: 'ebike_password';

            // DSN (Data Source Name): The connection string
            $dsn = "mysql:host={$host};port={$port};dbname={$database}; charset=utf8mb4";

            //crucial security & reliability settings
            $options = [
                // 1. Throw execptions on sql errors instead of failing silently

                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                // 2. Return databse rows as associative arrays (eg. $row['name'])
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // 3. Force real prepared statements to protect against SQL Injection

                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                // open the connection to MySQL
                self::$connection = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                // If connection false, throws a clean readble error
                throw new RuntimeException("Database Connection Error: " . $e->getMessage());
            }
        }

        return self::$connection;
    }
}
