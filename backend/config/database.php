<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv('SUNSOLAR_DB_HOST') ?: '127.0.0.1';
$username = getenv('SUNSOLAR_DB_USER') ?: '';
$password = getenv('SUNSOLAR_DB_PASSWORD') ?: '';
$database = getenv('SUNSOLAR_DB_NAME') ?: 'user_db';
$port = (int) (getenv('SUNSOLAR_DB_PORT') ?: '3307');

try {
    $conn = new mysqli($host, $username, $password, $database, $port);
    if (!$conn->connect_error) {
        $conn->set_charset('utf8mb4');
    }
} catch (Throwable $error) {
    error_log('Database connection failed: ' . $error->getMessage());
    $conn = null;
}
