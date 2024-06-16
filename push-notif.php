<?php
require './db_connection.php';

$email = $argv[1];
$subject = $argv[2];
$body = $argv[3];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email address: $email");
}

if (empty($subject)) {
    die("Subject cannot be empty");
}

if (empty($body)) {
    die("Body cannot be empty");
}

$idempotency_key = uniqid('notification_', true);

$sql = "INSERT INTO notifications (email, subject, body, idempotency_key) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $email, $subject, $body, $idempotency_key);
$stmt->execute();
$stmt->close();
$conn->close();
?>