<?php

// Load database prefix from prefix.json
$prefixFile = __DIR__ . '/prefix.json';
if (file_exists($prefixFile)) {
    $prefixData = json_decode(file_get_contents($prefixFile), true);
    $db_prefix = isset($prefixData['db_prefix']) ? $prefixData['db_prefix'] : '';
} else {
    $db_prefix = ''; // Default to no prefix if file not found
}

$host = 'localhost';
$port = 3306;
$db   = 'smartqueryai';
$user = 'smartqueryai';
$pass = 'smartqueryai';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Check if the PDO driver is available
    if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
        throw new Exception('PDO or PDO_MySQL extension is not enabled. Please enable it in your PHP configuration.');
    }

    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    // Log the error and provide a meaningful message
    error_log("Database connection error: " . $e->getMessage());
    die("A database connection error occurred. Please contact the administrator.");
}
?>