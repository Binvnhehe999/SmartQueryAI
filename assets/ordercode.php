<?php

global $db_prefix; // Declare global here (although not strictly necessary as db.php is included, it's good practice)
require_once __DIR__ . '/db.php';

// Function to get the real IP of the user
function getClientIP() {
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // If there are multiple IPs in the forwarded header, take the first one
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

$clientIP = getClientIP();

// Get the confirmation code from the GET parameter.
$code = $_GET['code'] ?? null;

if ($code) {
    $code = trim($code);

    // Fetch the code details from the database
    $stmt = $pdo->prepare("SELECT id, status, ip_count FROM {$db_prefix}confirmation_codes WHERE content = :code");
    $stmt->execute(['code' => $code]);
    $codeData = $stmt->fetch();

    if ($codeData) {
        // Mã hợp lệ được tìm thấy, **KHÔNG ÁP DỤNG RATE LIMITING**

        $codeId = $codeData['id'];
        $status = $codeData['status'];
        $ipCount = $codeData['ip_count'];

        $ipCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM {$db_prefix}confirmation_code_ips WHERE code_id = :code_id AND ip_address = :ip_address");
        $ipCheckStmt->execute(['code_id' => $codeId, 'ip_address' => $clientIP]);
        $ipExists = $ipCheckStmt->fetchColumn() > 0;

        if (!$ipExists) {
            // Add the IP to the database
            $addIpStmt = $pdo->prepare("INSERT INTO {$db_prefix}confirmation_code_ips (code_id, ip_address) VALUES (:code_id, :ip_address)");
            $addIpStmt->execute(['code_id' => $codeId, 'ip_address' => $clientIP]);

            // Update the IP count in the confirmation_codes table
            $ipCount++;
            $updateIpCountStmt = $pdo->prepare("UPDATE {$db_prefix}confirmation_codes SET ip_count = :ip_count WHERE id = :id");
            $updateIpCountStmt->execute(['ip_count' => $ipCount, 'id' => $codeId]);
        }

        // Change status if IP count exceeds the threshold
        if ($ipCount >= 3 && $status === 'active') {
            $status = 'multi-direction warning';
            $updateStatusStmt = $pdo->prepare("UPDATE {$db_prefix}confirmation_codes SET status = :status WHERE id = :id");
            $updateStatusStmt->execute(['status' => $status, 'id' => $codeId]);
        }

        // Respond with the current status
        echo json_encode(['status' => $status]);

    } else {
        // **Mã KHÔNG hợp lệ, ÁP DỤNG RATE LIMITING**

        // ===== Rate Limiting / Throttling Implementation =====
        // Configuration: Maximum attempts per time window and backoff settings.
        $attemptLimit = 5;           // maximum allowed attempts per time window
        $timeWindowSeconds = 60;     // time window (in seconds)
        $exponentialBackoffBase = 30; // base delay in seconds

        // Count the number of attempts from this IP within the past time window.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$db_prefix}confirmation_code_attempts WHERE ip_address = :ip_address AND attempt_time > DATE_SUB(NOW(), INTERVAL :timeWindow SECOND)");
        $stmt->bindValue(':ip_address', $clientIP);
        $stmt->bindValue(':timeWindow', $timeWindowSeconds, PDO::PARAM_INT);
        $stmt->execute();
        $attemptCount = (int)$stmt->fetchColumn();

        // If the attempt count exceeds (or equals) our limit, compute a backoff delay and return an error.
        if ($attemptCount >= $attemptLimit) {
            // For example, after reaching the limit, use exponential backoff:
            $backoffDelay = $exponentialBackoffBase * pow(2, $attemptCount - $attemptLimit);
            echo json_encode([
                'status' => 'error',
                'message' => "Too many attempts. Please wait {$backoffDelay} seconds before trying again."
            ]);
            exit;
        }

        // Log the current attempt into the confirmation_code_attempts table.
        $stmt = $pdo->prepare("INSERT INTO {$db_prefix}confirmation_code_attempts (ip_address, attempt_time) VALUES (:ip_address, NOW())");
        $stmt->execute(['ip_address' => $clientIP]);
        // ===== End Rate Limiting Section =====

        // Code not found in the database
        echo json_encode(['status' => 'not found']);
    }
} else {
    // No code provided, **KHÔNG ÁP DỤNG RATE LIMITING** (vì không biết có phải là thử mã sai hay không)
    // Chúng ta có thể chọn áp dụng rate limiting ở đây nếu muốn chặn các request không có code,
    // nhưng trong trường hợp này, chúng ta chỉ áp dụng khi *thực sự* thử mã sai.

    // No code provided
    echo json_encode(['status' => 'no code provided']);
}
?>