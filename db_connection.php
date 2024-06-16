<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


$host = $_ENV['DB_HOST'];
$port = $_ENV['DB_PORT'];
$user = $_ENV['DB_USER'];
$password = $_ENV['DB_PASSWORD'];
$dbname = $_ENV['DB_NAME'];

function connect_to_db($host, $user, $password, $dbname, $port) {
    $mysqli = null;
    $attempts = 0;
    $max_attempts = 5;

    while (!$mysqli && $attempts < $max_attempts) {
        try {
            $mysqli = new mysqli($host, $user, $password, $dbname, $port);
            if ($mysqli->connect_error) {
                throw new Exception("Connection failed: ". $mysqli->connect_error);
            }
        } catch (Exception $e) {
            echo "Attempt #$attempts failed: ". $e->getMessage(). "\n";
            sleep(5);
            $attempts++;
        }
    }

    return $mysqli;
}

$conn = connect_to_db($host, $user, $password, $dbname, $port);

if ($conn === null) {
    die("Failed to establish a database connection after several attempts.");
}

echo "Connected successfully\n";
?>