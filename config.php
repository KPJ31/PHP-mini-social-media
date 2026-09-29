<?php
require_once __DIR__ . '/security.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli(
        getenv('DB_HOST') ?: 'localhost',
        getenv('DB_USER') !== false ? getenv('DB_USER') : 'root',
        getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
        getenv('DB_NAME') ?: 'mini_social_network',
        (int) (getenv('DB_PORT') ?: 3306)
    );
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    failRequest(503, 'The database is unavailable. Please check the database configuration.');
}
