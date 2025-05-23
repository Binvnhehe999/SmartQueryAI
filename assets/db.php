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
$db   = 'Database name';
$user = 'Database User';
$pass = 'Database Password';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>