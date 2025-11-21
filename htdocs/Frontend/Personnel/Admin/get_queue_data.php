<?php
session_start();
require_once __DIR__ . '/../../Student/db_config.php';
require_once __DIR__ . '/../admin_functions.php';

header('Content-Type: application/json');

// Get database connection
$conn = getDBConnection();

try {
    // Get current serving queue
    $currentlyServing = getCurrentlyServing($conn);
    $currentQueue = !empty($currentlyServing) ? $currentlyServing[0] : null;
    
    // Get current queue details if available
    $currentQueueDetails = null;
    $currentServices = [];
    if ($currentQueue) {
        $currentQueueDetails = getQueueDetails($conn, $currentQueue['id']);
        if ($currentQueueDetails && $currentQueueDetails['services']) {
            $currentServices = explode(', ', $currentQueueDetails['services']);
        }
    }
    
    // Get waiting queues (active)
    $waitingQueues = getWaitingQueuesList($conn, 50);
    
    // Get stalled queues
    $stalledQuery = "SELECT * FROM queues WHERE status = 'stalled' AND DATE(created_at) = CURDATE() ORDER BY created_at ASC";
    $stalledResult = $conn->query($stalledQuery);
    $stalledQueues = $stalledResult->fetch_all(MYSQLI_ASSOC);
    
    // Get skipped queues
    $skippedQuery = "SELECT * FROM queues WHERE status = 'skipped' AND DATE(created_at) = CURDATE() ORDER BY created_at ASC";
    $skippedResult = $conn->query($skippedQuery);
    $skippedQueues = $skippedResult->fetch_all(MYSQLI_ASSOC);
    
    // Get pause state
    $isPaused = isset($_SESSION['queue_paused']) ? $_SESSION['queue_paused'] : false;
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'currentQueue' => $currentQueue,
        'currentQueueDetails' => $currentQueueDetails,
        'currentServices' => $currentServices,
        'activeQueues' => $waitingQueues,
        'stalledQueues' => $stalledQueues,
        'skippedQueues' => $skippedQueues,
        'isPaused' => $isPaused
    ]);
    
} catch (Exception $e) {
    error_log("Get queue data error: " . $e->getMessage());
    if (isset($conn)) {
        $conn->close();
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch queue data'
    ]);
}
?>