<?php
// track.php - Handle QR code scans and redirect to queue status
session_start();
require_once 'db_config.php';

// Get queue number from URL parameter
$queueNumber = $_GET['queue'] ?? '';

if (empty($queueNumber)) {
    header("Location: index.php");
    exit();
}

try {
    $conn = getDBConnection();
    
    // Verify queue exists and get queue ID
    $stmt = $conn->prepare("SELECT id FROM queues WHERE queue_number = ? AND DATE(created_at) = CURDATE()");
    $stmt->bind_param("s", $queueNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Queue not found
        $conn->close();
        header("Location: index.php?error=queue_not_found");
        exit();
    }
    
    $queueData = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    // Store queue number in session for Phone.php to access
    $_SESSION['tracking_queue'] = $queueNumber;
    
    // Log the QR scan
    $conn = getDBConnection();
    logQueueAction($conn, $queueData['id'], 'qr_scanned', 'QR code scanned for mobile tracking');
    $conn->close();
    
    // Redirect to Phone.php
    header("Location: Phone.php?queue=" . urlencode($queueNumber));
    exit();
    
} catch (Exception $e) {
    error_log("Error in track.php: " . $e->getMessage());
    header("Location: index.php?error=tracking_failed");
    exit();
}
?>