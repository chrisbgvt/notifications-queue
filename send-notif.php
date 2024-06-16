<?php
require './db_connection.php';
require './mailersend.php';

$maxRetries = 5;
$retryDelay = 10;
$networkRetryDelay = 60;

function reconnectToDatabase() {
    global $host, $user, $password, $dbname, $port, $conn;
    $conn = connect_to_db($host, $user, $password, $dbname, $port);
    if ($conn === null) {
        throw new Exception("Failed to establish a database connection after several attempts.");
    }
}

while (true) {
    try {
        if (!$conn->ping()) {
            reconnectToDatabase();
        }

        $sql = "SELECT * FROM notifications WHERE status = 'pending' AND retry_count < 5 ORDER BY created_at LIMIT 10";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $email = $row['email'];
                $subject = $row['subject'];
                $body = $row['body'];
                $idempotencyKey = $row['idempotency_key'];

                $checkSql = "SELECT * FROM sent_notifications WHERE idempotency_key = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("s", $idempotencyKey);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $checkStmt->close();

                if ($checkResult->num_rows > 0) {
                    continue;
                }

                $retryCount = 0;
                $emailSent = false;

                while (!$emailSent && $retryCount < $maxRetries) {
                    try {
                        sendEmail($email, $subject, $body);
                        $emailSent = true;
                    } catch (Exception $e) {
                        $retryCount++;
                        if ($retryCount < $maxRetries) {
                            sleep($retryDelay);
                        } else {
                            print("Error sending email after $maxRetries retries: " . $e->getMessage());
                        }
                    }
                }

                if ($emailSent) {
                    $updateSql = "UPDATE notifications SET status = 'sent', last_attempt = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($updateSql);
                    $stmt->bind_param("i", $row['id']);
                    $stmt->execute();
                    $stmt->close();

                    $insertSql = "INSERT INTO sent_notifications (idempotency_key, notification_id) VALUES (?, ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    $insertStmt->bind_param("si", $idempotencyKey, $row['id']);
                    $insertStmt->execute();
                    $insertStmt->close();
                } else {
                    $updateSql = "UPDATE notifications SET status = 'failed', retry_count = retry_count + 1, last_attempt = NOW() WHERE id = ?";
                    $stmt = $conn->prepare($updateSql);
                    $stmt->bind_param("i", $row['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    } catch (Exception $e) {
        error_log("Script encountered an error: " . $e->getMessage());
        reconnectToDatabase();
    }

    sleep(5);
}

$conn->close();
?>