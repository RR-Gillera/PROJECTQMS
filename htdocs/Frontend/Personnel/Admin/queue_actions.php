<?php
session_start();

// Check if it's an AJAX request - FIXED
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

require_once __DIR__ . '/../../Student/db_config.php';
require_once __DIR__ . '/../admin_functions.php';

// Get action and queue ID
$action = $_GET['action'] ?? '';
$queueId = $_GET['id'] ?? null;

// Get database connection
$conn = getDBConnection();

try {
    switch ($action) {
        case 'complete':
            // Mark current queue as completed
            if ($queueId) {
                // Update current queue to completed
                $stmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'completed',
                        completed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $queueId);
                $stmt->execute();
                $stmt->close();
                
                // Log the action
                logQueueAction($conn, $queueId, 'completed', 'Queue marked as completed');
                
                // Get next queue and serve it
                $nextQueue = getNextQueueToServe($conn);
                if ($nextQueue) {
                    $stmt = $conn->prepare("
                        UPDATE queues 
                        SET status = 'serving',
                            window_number = 1,
                            served_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->bind_param("i", $nextQueue['id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    logQueueAction($conn, $nextQueue['id'], 'serving', 'Queue now being served at counter 1');
                }
            }
            break;
            
        case 'stall':
            // Mark current queue as stalled
            if ($queueId) {
                // Update to stalled status
                $stmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'stalled',
                        window_number = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $queueId);
                $stmt->execute();
                $stmt->close();
                
                // Log the action
                logQueueAction($conn, $queueId, 'stalled', 'Queue marked as stalled');
                
                // Get next queue and serve it
                $nextQueue = getNextQueueToServe($conn);
                if ($nextQueue) {
                    $stmt = $conn->prepare("
                        UPDATE queues 
                        SET status = 'serving',
                            window_number = 1,
                            served_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->bind_param("i", $nextQueue['id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    logQueueAction($conn, $nextQueue['id'], 'serving', 'Queue now being served at counter 1');
                }
            }
            break;
            
        case 'skip':
            // Mark current queue as skipped
            if ($queueId) {
                // Update to skipped status
                $stmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'skipped',
                        window_number = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $queueId);
                $stmt->execute();
                $stmt->close();
                
                // Log the action
                logQueueAction($conn, $queueId, 'skipped', 'Queue skipped');
                
                // Get next queue and serve it
                $nextQueue = getNextQueueToServe($conn);
                if ($nextQueue) {
                    $stmt = $conn->prepare("
                        UPDATE queues 
                        SET status = 'serving',
                            window_number = 1,
                            served_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->bind_param("i", $nextQueue['id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    logQueueAction($conn, $nextQueue['id'], 'serving', 'Queue now being served at counter 1');
                }
            }
            break;
            
        case 'next':
            // Call next queue (without completing current)
            // First, reset any currently serving queue back to waiting
            $resetStmt = $conn->prepare("
                UPDATE queues 
                SET status = 'waiting',
                    window_number = NULL,
                    served_at = NULL
                WHERE status = 'serving'
                AND DATE(created_at) = CURDATE()
            ");
            $resetStmt->execute();
            $resetStmt->close();
            
            // Get next queue and serve it
            $nextQueue = getNextQueueToServe($conn);
            if ($nextQueue) {
                $stmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        window_number = 1,
                        served_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $nextQueue['id']);
                $stmt->execute();
                $stmt->close();
                
                logQueueAction($conn, $nextQueue['id'], 'serving', 'Queue called to counter 1');
            }
            break;
            
        case 'resume':
            // Resume a stalled queue
            if ($queueId) {
                $stmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        window_number = 1,
                        served_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $queueId);
                $stmt->execute();
                $stmt->close();
                
                logQueueAction($conn, $queueId, 'resumed', 'Stalled queue resumed');
            }
            break;
            
        case 'toggle_pause':
            // Toggle the pause state in session
            $_SESSION['queue_paused'] = !isset($_SESSION['queue_paused']) ? true : !$_SESSION['queue_paused'];
            
            // Log the action
            $pauseState = $_SESSION['queue_paused'] ? 'paused' : 'resumed';
            logQueueAction($conn, null, $pauseState, 'Queue system ' . $pauseState);
            
            break;
            
        default:
            $conn->close();
            if ($isAjax) {
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                exit;
            } else {
                header('Location: Queue.php');
                exit;
            }
    }
    
    $conn->close();
    
    // Return appropriate response
    if ($isAjax) {
        echo json_encode(['success' => true]);
        exit;
    } else {
        header('Location: Queue.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Queue action error: " . $e->getMessage());
    if (isset($conn)) {
        $conn->close();
    }
    
    if ($isAjax) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } else {
        header('Location: Queue.php?error=1');
        exit;
    }
}
?>