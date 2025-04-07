<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php'; // Load thư viện

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/assets/');
$dotenv->load();

echo 'DB_HOST: ' . $_ENV['DB_HOST'];
?>
