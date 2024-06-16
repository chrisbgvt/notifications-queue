<?php
require './db_connection.php';
require './mailersend.php';

while (true) {
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

            try {
                sendEmail($email, $subject, $body);
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
            } catch (Exception $e) {
                print("Error sending email: " . $e->getMessage());
                $updateSql = "UPDATE notifications SET status = 'failed', retry_count = retry_count + 1, last_attempt = NOW() WHERE id = ?";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("i", $row['id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    sleep(5);
}
$conn->close();
?>