<?php
// cancel_queue.php - Handle queue cancellation requests from mobile
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// Get queue number from POST request
$queueNumber = $_POST['queue_number'] ?? '';

if (empty($queueNumber)) {
    echo json_encode([
        'success' => false,
        'message' => 'Queue number is required'
    ]);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Check if queue exists and is cancellable
    $stmt = $conn->prepare("
        SELECT id, status 
        FROM queues 
        WHERE queue_number = ? 
        AND DATE(created_at) = CURDATE()
    ");
    
    $stmt->bind_param("s", $queueNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        echo json_encode([
            'success' => false,
            'message' => 'Queue not found'
        ]);
        exit();
    }
    
    $queueData = $result->fetch_assoc();
    $stmt->close();
    
    // Check if queue can be cancelled
    if ($queueData['status'] === 'completed') {
        $conn->close();
        echo json_encode([
            'success' => false,
            'message' => 'Cannot cancel a completed queue'
        ]);
        exit();
    }
    
    // Update queue status to cancelled
    $updateStmt = $conn->prepare("
        UPDATE queues 
        SET status = 'cancelled',
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $updateStmt->bind_param("i", $queueData['id']);
    
    if (!$updateStmt->execute()) {
        $updateStmt->close();
        $conn->close();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to cancel queue'
        ]);
        exit();
    }
    
    $updateStmt->close();
    
    // Log the cancellation
    logQueueAction($conn, $queueData['id'], 'cancelled', 'Queue cancelled by user via mobile');
    
    $conn->close();
    
    // Clear session if exists
    if (isset($_SESSION['tracking_queue']) && $_SESSION['tracking_queue'] === $queueNumber) {
        unset($_SESSION['tracking_queue']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Queue cancelled successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error cancelling queue: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while cancelling the queue'
    ]);
}
?>