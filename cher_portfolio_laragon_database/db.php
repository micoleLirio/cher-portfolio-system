<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Laragon MySQL Database Connection
|--------------------------------------------------------------------------
| Default Laragon credentials:
| Host: 127.0.0.1
| Username: root
| Password: blank
|
| Change these values only if your MySQL settings are different.
*/

const DB_HOST = '127.0.0.1';
const DB_NAME = 'db_cher_portfolio';
const DB_USER = 'root';
const DB_PASS = '';

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST
         . ';dbname=' . DB_NAME
         . ';charset=utf8mb4';

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    return $pdo;
}
