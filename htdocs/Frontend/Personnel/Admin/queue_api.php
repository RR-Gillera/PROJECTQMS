<?php
/**
 * Backend API for Admin Queue Management
 * Handles queue operations for admin personnel
 */

session_start();
require_once __DIR__ . '/../../Student/db_config.php';
require_once __DIR__ . '/../admin_functions.php';

header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$studentId = $_SESSION['user']['studentId'];
// Admin can manage all counters, so we don't require a specific counter assignment
$counterNumber = 1; // Default counter for admin operations

$conn = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Auto-cancel skipped queues that haven't been resumed after 1 hour
// Check if skipped_at column exists, if not use updated_at
try {
    // First, check if we need to add skipped_at column
    $checkColumnStmt = $conn->query("SHOW COLUMNS FROM queues LIKE 'skipped_at'");
    $hasSkippedAtColumn = $checkColumnStmt->num_rows > 0;
    $checkColumnStmt->close();
    
    if ($hasSkippedAtColumn) {
        // Use skipped_at column for precise tracking
        $autoCancelStmt = $conn->prepare("
            UPDATE queues 
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE status = 'skipped'
            AND DATE(created_at) = CURDATE()
            AND skipped_at IS NOT NULL
            AND TIMESTAMPDIFF(MINUTE, skipped_at, NOW()) >= 60
        ");
    } else {
        // Fallback to updated_at (less precise but functional)
        $autoCancelStmt = $conn->prepare("
            UPDATE queues 
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE status = 'skipped'
            AND DATE(created_at) = CURDATE()
            AND TIMESTAMPDIFF(MINUTE, updated_at, NOW()) >= 60
        ");
    }
    
    $autoCancelStmt->execute();
    $cancelledCount = $autoCancelStmt->affected_rows;
    $autoCancelStmt->close();
    
    // Log auto-cancelled queues
    if ($cancelledCount > 0) {
        error_log("Auto-cancelled $cancelledCount skipped queues after 1 hour");
        
        // Log the action for each cancelled queue
        if ($hasSkippedAtColumn) {
            $logStmt = $conn->prepare("
                SELECT id FROM queues 
                WHERE status = 'cancelled' 
                AND DATE(updated_at) = CURDATE()
                ORDER BY updated_at DESC 
                LIMIT ?
            ");
            $logStmt->bind_param("i", $cancelledCount);
            $logStmt->execute();
            $result = $logStmt->get_result();
            while ($row = $result->fetch_assoc()) {
                logQueueAction($conn, $row['id'], 'auto-cancelled', 'Automatically cancelled after 1 hour of being skipped');
            }
            $logStmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Auto-cancel error: " . $e->getMessage());
}

try {
    switch ($action) {
        case 'get_current_queue':
            // Get current queue being served (admin can see any counter)
            $stmt = $conn->prepare("
                SELECT 
                    q.*,
                    GROUP_CONCAT(qs.service_name SEPARATOR ', ') as services
                FROM queues q
                LEFT JOIN queue_services qs ON q.id = qs.queue_id
                WHERE q.status = 'serving'
                AND DATE(q.created_at) = CURDATE()
                GROUP BY q.id
                ORDER BY q.served_at DESC
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $currentQueue = $result->fetch_assoc();
            $stmt->close();
            
            // Get waiting queues (will be filtered by priority)
            $waitingStmt = $conn->prepare("
                SELECT 
                    q.id,
                    q.queue_number,
                    q.queue_type,
                    q.student_name,
                    q.student_id,
                    q.year_level,
                    q.course_program,
                    q.services_count,
                    q.created_at,
                    TIMESTAMPDIFF(MINUTE, q.created_at, NOW()) as wait_time_minutes
                FROM queues q
                WHERE q.status = 'waiting'
                AND DATE(q.created_at) = CURDATE()
                ORDER BY 
                    CASE WHEN q.queue_type = 'priority' THEN 0 ELSE 1 END,
                    q.created_at ASC
            ");
            $waitingStmt->execute();
            $waitingResult = $waitingStmt->get_result();
            $waitingQueues = $waitingResult->fetch_all(MYSQLI_ASSOC);
            $waitingStmt->close();
            
            // Get all stalled queues (admin can see all)
            $stalledStmt = $conn->prepare("
                SELECT 
                    q.*,
                    GROUP_CONCAT(qs.service_name SEPARATOR ', ') as services,
                    TIMESTAMPDIFF(MINUTE, q.created_at, NOW()) as wait_time_minutes
                FROM queues q
                LEFT JOIN queue_services qs ON q.id = qs.queue_id
                WHERE q.status = 'stalled'
                AND DATE(q.created_at) = CURDATE()
                GROUP BY q.id
                ORDER BY q.created_at ASC
            ");
            $stalledStmt->execute();
            $stalledResult = $stalledStmt->get_result();
            $stalledQueues = $stalledResult->fetch_all(MYSQLI_ASSOC);
            $stalledStmt->close();
            
            // Get all skipped queues (admin can see all)
            $skippedStmt = $conn->prepare("
                SELECT 
                    q.*,
                    GROUP_CONCAT(qs.service_name SEPARATOR ', ') as services,
                    TIMESTAMPDIFF(MINUTE, q.created_at, NOW()) as wait_time_minutes
                FROM queues q
                LEFT JOIN queue_services qs ON q.id = qs.queue_id
                WHERE q.status = 'skipped'
                AND DATE(q.created_at) = CURDATE()
                GROUP BY q.id
                ORDER BY q.created_at ASC
            ");
            $skippedStmt->execute();
            $skippedResult = $skippedStmt->get_result();
            $skippedQueues = $skippedResult->fetch_all(MYSQLI_ASSOC);
            $skippedStmt->close();
            
            // Get statistics for all queues (admin sees everything)
            $statsStmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'stalled' THEN 1 ELSE 0 END) as stalled,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    AVG(CASE 
                        WHEN status = 'completed' AND served_at IS NOT NULL AND completed_at IS NOT NULL
                        THEN TIMESTAMPDIFF(MINUTE, served_at, completed_at)
                        ELSE NULL
                    END) as avg_service_time
                FROM queues
                WHERE DATE(created_at) = CURDATE()
            ");
            $statsStmt->execute();
            $statsResult = $statsStmt->get_result();
            $stats = $statsResult->fetch_assoc();
            $statsStmt->close();
            
            // Format current queue data
            if ($currentQueue) {
                $createdAt = new DateTime($currentQueue['created_at']);
                $currentQueue['time_requested'] = $createdAt->format('M d, Y g:i A');
                
                if ($currentQueue['served_at']) {
                    $servedAt = new DateTime($currentQueue['served_at']);
                    $waitTime = $servedAt->diff($createdAt);
                    $currentQueue['wait_time'] = $waitTime->h . 'h ' . $waitTime->i . 'm';
                } else {
                    $currentQueue['wait_time'] = '--';
                }
                
                $currentQueue['services'] = $currentQueue['services'] ? explode(', ', $currentQueue['services']) : [];
            }
            
            echo json_encode([
                'success' => true,
                'currentQueue' => $currentQueue,
                'waitingQueues' => $waitingQueues,
                'stalledQueues' => $stalledQueues,
                'skippedQueues' => $skippedQueues,
                'statistics' => [
                    'avgServiceTime' => round($stats['avg_service_time'] ?? 0) . ' min',
                    'completed' => (int)($stats['completed'] ?? 0),
                    'stalled' => (int)($stats['stalled'] ?? 0),
                    'cancelled' => (int)($stats['cancelled'] ?? 0)
                ],
                'counterNumber' => $counterNumber
            ]);
            break;
            
        case 'next':
            // Call next queue without completing current one
            // Reset any currently serving queue back to waiting
            $resetStmt = $conn->prepare("
                UPDATE queues 
                SET status = 'waiting',
                    window_number = NULL,
                    served_at = NULL,
                    updated_at = NOW()
                WHERE status = 'serving'
                AND DATE(created_at) = CURDATE()
            ");
            $resetStmt->execute();
            $resetStmt->close();

            // Get next queue (admin uses default counter logic)
            $nextQueue = getNextQueueToServe($conn);
            if ($nextQueue) {
                $nextStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        counter_id = ?,
                        window_number = ?,
                        served_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $nextStmt->bind_param("iii", $counterNumber, $counterNumber, $nextQueue['id']);
                $nextStmt->execute();
                $nextStmt->close();

                logQueueAction($conn, $nextQueue['id'], 'serving', "Queue called by admin");
            }

            echo json_encode(['success' => true, 'message' => 'Next queue called']);
            break;

        case 'complete':
            // Complete current queue and get next
            $queueId = $_POST['queue_id'] ?? null;
            
            if (!$queueId) {
                throw new Exception('Queue ID is required');
            }
            
            // Admin can complete any queue - just verify it exists
            $verifyStmt = $conn->prepare("
                SELECT id FROM queues WHERE id = ?
            ");
            $verifyStmt->bind_param("i", $queueId);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult->num_rows === 0) {
                $verifyStmt->close();
                throw new Exception('Queue not found');
            }
            $verifyStmt->close();
            
            // Mark as completed
            $completeStmt = $conn->prepare("
                UPDATE queues 
                SET status = 'completed',
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $completeStmt->bind_param("i", $queueId);
            $completeStmt->execute();
            $completeStmt->close();
            
            logQueueAction($conn, $queueId, 'completed', "Completed by admin");
            
            // Get next queue
            $nextQueue = getNextQueueToServe($conn);
            if ($nextQueue) {
                $nextStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        counter_id = ?,
                        window_number = ?,
                        served_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $nextStmt->bind_param("iii", $counterNumber, $counterNumber, $nextQueue['id']);
                $nextStmt->execute();
                $nextStmt->close();
                
                logQueueAction($conn, $nextQueue['id'], 'serving', "Now being served");
            }
            
            echo json_encode(['success' => true, 'message' => 'Queue completed']);
            break;
            
        case 'stall':
            // Mark queue as stalled
            $queueId = $_POST['queue_id'] ?? null;
            
            if (!$queueId) {
                throw new Exception('Queue ID is required');
            }
            
            // Admin can stall any queue - just verify it exists
            $verifyStmt = $conn->prepare("
                SELECT id FROM queues WHERE id = ?
            ");
            $verifyStmt->bind_param("i", $queueId);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult->num_rows === 0) {
                $verifyStmt->close();
                throw new Exception('Queue not found');
            }
            $verifyStmt->close();
            
            $stallStmt = $conn->prepare("
                UPDATE queues 
                SET status = 'stalled',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stallStmt->bind_param("i", $queueId);
            $stallStmt->execute();
            $stallStmt->close();
            
            logQueueAction($conn, $queueId, 'stalled', "Stalled by admin");
            
            // Get next queue
            $nextQueue = getNextQueueToServe($conn);
            if ($nextQueue) {
                $nextStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        counter_id = ?,
                        window_number = ?,
                        served_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $nextStmt->bind_param("iii", $counterNumber, $counterNumber, $nextQueue['id']);
                $nextStmt->execute();
                $nextStmt->close();
                
                logQueueAction($conn, $nextQueue['id'], 'serving', "Now being served");
            }
            
            echo json_encode(['success' => true, 'message' => 'Queue stalled']);
            break;
            
        case 'skip':
            // Skip queue
            $queueId = $_POST['queue_id'] ?? null;
            
            if (!$queueId) {
                throw new Exception('Queue ID is required');
            }
            
            // Admin can skip any queue - just verify it exists
            $verifyStmt = $conn->prepare("
                SELECT id FROM queues WHERE id = ?
            ");
            $verifyStmt->bind_param("i", $queueId);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult->num_rows === 0) {
                $verifyStmt->close();
                throw new Exception('Queue not found');
            }
            $verifyStmt->close();
            
            // Check if skipped_at column exists
            $checkColumnStmt = $conn->query("SHOW COLUMNS FROM queues LIKE 'skipped_at'");
            $hasSkippedAtColumn = $checkColumnStmt->num_rows > 0;
            $checkColumnStmt->close();
            
            if ($hasSkippedAtColumn) {
                $skipStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'skipped',
                        skipped_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
            } else {
                $skipStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'skipped',
                        updated_at = NOW()
                    WHERE id = ?
                ");
            }
            $skipStmt->bind_param("i", $queueId);
            $skipStmt->execute();
            $skipStmt->close();
            
            logQueueAction($conn, $queueId, 'skipped', "Skipped by admin");
            
            // Get next queue
            $nextQueue = getNextQueueToServe($conn);
            if ($nextQueue) {
                $nextStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        counter_id = ?,
                        window_number = ?,
                        served_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $nextStmt->bind_param("iii", $counterNumber, $counterNumber, $nextQueue['id']);
                $nextStmt->execute();
                $nextStmt->close();
                
                logQueueAction($conn, $nextQueue['id'], 'serving', "Now being served");
            }
            
            echo json_encode(['success' => true, 'message' => 'Queue skipped']);
            break;
            
        case 'resume':
            // Resume a stalled or skipped queue
            $queueId = $_POST['queue_id'] ?? null;
            
            if (!$queueId) {
                throw new Exception('Queue ID is required');
            }
            
            // Verify this queue exists and is stalled or skipped
            $verifyStmt = $conn->prepare("
                SELECT id, status FROM queues 
                WHERE id = ? AND (status = 'stalled' OR status = 'skipped')
            ");
            $verifyStmt->bind_param("i", $queueId);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult->num_rows === 0) {
                $verifyStmt->close();
                throw new Exception('Queue not found or cannot be resumed');
            }
            $verifyStmt->close();
            
            // Check if there's already a queue being served
            $currentStmt = $conn->prepare("
                SELECT id FROM queues 
                WHERE status = 'serving' AND DATE(created_at) = CURDATE()
            ");
            $currentStmt->execute();
            $currentResult = $currentStmt->get_result();
            
            if ($currentResult->num_rows > 0) {
                $currentStmt->close();
                throw new Exception('Please complete or stall the current queue before resuming another');
            }
            $currentStmt->close();
            
            // Check if skipped_at column exists
            $checkColumnStmt = $conn->query("SHOW COLUMNS FROM queues LIKE 'skipped_at'");
            $hasSkippedAtColumn = $checkColumnStmt->num_rows > 0;
            $checkColumnStmt->close();
            
            // Resume the queue by setting it back to serving and clearing skipped_at
            if ($hasSkippedAtColumn) {
                $resumeStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        served_at = NOW(),
                        updated_at = NOW(),
                        skipped_at = NULL
                    WHERE id = ?
                ");
            } else {
                $resumeStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        served_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
            }
            $resumeStmt->bind_param("i", $queueId);
            $resumeStmt->execute();
            $resumeStmt->close();
            
            logQueueAction($conn, $queueId, 'resumed', "Resumed by admin");
            
            echo json_encode(['success' => true, 'message' => 'Queue resumed']);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Queue API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>

