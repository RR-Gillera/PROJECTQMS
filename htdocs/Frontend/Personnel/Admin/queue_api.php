<?php
/**
 * Backend API for Working Queue Management
 * Handles queue operations for workers at their assigned counters
 */

session_start();
require_once __DIR__ . '/../../Student/db_config.php';
require_once __DIR__ . '/../admin_functions.php';

header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in and is a worker
if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role'] ?? '') !== 'working') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$studentId = $_SESSION['user']['studentId'];
$counterNumber = $_SESSION['user']['counterNumber'] ?? null;

if (!$counterNumber) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No counter assignment found']);
    exit;
}

$conn = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_current_queue':
            // Get current queue being served at this counter
            $stmt = $conn->prepare("
                SELECT 
                    q.*,
                    GROUP_CONCAT(qs.service_name SEPARATOR ', ') as services
                FROM queues q
                LEFT JOIN queue_services qs ON q.id = qs.queue_id
                WHERE q.counter_id = ? 
                AND q.status = 'serving'
                AND DATE(q.created_at) = CURDATE()
                GROUP BY q.id
                ORDER BY q.served_at DESC
                LIMIT 1
            ");
            $stmt->bind_param("i", $counterNumber);
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
            
            // Get stalled queues for this counter
            $stalledStmt = $conn->prepare("
                SELECT 
                    q.*,
                    GROUP_CONCAT(qs.service_name SEPARATOR ', ') as services
                FROM queues q
                LEFT JOIN queue_services qs ON q.id = qs.queue_id
                WHERE q.counter_id = ?
                AND q.status = 'stalled'
                AND DATE(q.created_at) = CURDATE()
                GROUP BY q.id
                ORDER BY q.created_at ASC
            ");
            $stalledStmt->bind_param("i", $counterNumber);
            $stalledStmt->execute();
            $stalledResult = $stalledStmt->get_result();
            $stalledQueues = $stalledResult->fetch_all(MYSQLI_ASSOC);
            $stalledStmt->close();
            
            // Get skipped queues for this counter
            $skippedStmt = $conn->prepare("
                SELECT 
                    q.*,
                    GROUP_CONCAT(qs.service_name SEPARATOR ', ') as services
                FROM queues q
                LEFT JOIN queue_services qs ON q.id = qs.queue_id
                WHERE q.counter_id = ?
                AND q.status = 'skipped'
                AND DATE(q.created_at) = CURDATE()
                GROUP BY q.id
                ORDER BY q.created_at ASC
            ");
            $skippedStmt->bind_param("i", $counterNumber);
            $skippedStmt->execute();
            $skippedResult = $skippedStmt->get_result();
            $skippedQueues = $skippedResult->fetch_all(MYSQLI_ASSOC);
            $skippedStmt->close();
            
            // Get statistics for this counter
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
                WHERE counter_id = ?
                AND DATE(created_at) = CURDATE()
            ");
            $statsStmt->bind_param("i", $counterNumber);
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
            // Reset any currently serving queue for this counter back to waiting
            $resetStmt = $conn->prepare("
                UPDATE queues 
                SET status = 'waiting',
                    window_number = NULL,
                    served_at = NULL,
                    updated_at = NOW()
                WHERE status = 'serving'
                AND counter_id = ?
                AND DATE(created_at) = CURDATE()
            ");
            $resetStmt->bind_param("i", $counterNumber);
            $resetStmt->execute();
            $resetStmt->close();

            // Get next queue that matches this counter's type
            $nextQueue = getNextQueueForCounter($conn, $counterNumber);
            if ($nextQueue) {
                $nextStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        counter_id = ?,
                        window_number = ?,
                        served_at = NOW(),
                        updated_at = NOW(),
                        worker_id = ?
                    WHERE id = ?
                ");
                $nextStmt->bind_param("iisi", $counterNumber, $counterNumber, $studentId, $nextQueue['id']);
                $nextStmt->execute();
                $nextStmt->close();

                logQueueAction($conn, $nextQueue['id'], 'serving', "Queue called to counter $counterNumber");
            }

            echo json_encode(['success' => true, 'message' => 'Next queue called']);
            break;

        case 'complete':
            // Complete current queue and get next
            $queueId = $_POST['queue_id'] ?? null;
            
            if (!$queueId) {
                throw new Exception('Queue ID is required');
            }
            
            // Verify this queue belongs to this counter
            $verifyStmt = $conn->prepare("
                SELECT id, counter_id FROM queues 
                WHERE id = ? AND counter_id = ?
            ");
            $verifyStmt->bind_param("ii", $queueId, $counterNumber);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult->num_rows === 0) {
                $verifyStmt->close();
                throw new Exception('Queue not found or not assigned to this counter');
            }
            $verifyStmt->close();
            
            // Mark as completed
            $completeStmt = $conn->prepare("
                UPDATE queues 
                SET status = 'completed',
                    completed_at = NOW(),
                    updated_at = NOW(),
                    worker_id = ?
                WHERE id = ?
            ");
            $completeStmt->bind_param("si", $studentId, $queueId);
            $completeStmt->execute();
            $completeStmt->close();
            
            logQueueAction($conn, $queueId, 'completed', "Completed by worker at counter $counterNumber");
            
            // Get next queue that matches this counter's type
            $nextQueue = getNextQueueForCounter($conn, $counterNumber);
            if ($nextQueue) {
                $nextStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        counter_id = ?,
                        window_number = ?,
                        served_at = NOW(),
                        updated_at = NOW(),
                        worker_id = ?
                    WHERE id = ?
                ");
                $nextStmt->bind_param("iisi", $counterNumber, $counterNumber, $studentId, $nextQueue['id']);
                $nextStmt->execute();
                $nextStmt->close();
                
                logQueueAction($conn, $nextQueue['id'], 'serving', "Now being served at counter $counterNumber");
            }
            
            echo json_encode(['success' => true, 'message' => 'Queue completed']);
            break;
            
        case 'stall':
            // Mark queue as stalled
            $queueId = $_POST['queue_id'] ?? null;
            
            if (!$queueId) {
                throw new Exception('Queue ID is required');
            }
            
            // Verify this queue belongs to this counter
            $verifyStmt = $conn->prepare("
                SELECT id, counter_id FROM queues 
                WHERE id = ? AND counter_id = ?
            ");
            $verifyStmt->bind_param("ii", $queueId, $counterNumber);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult->num_rows === 0) {
                $verifyStmt->close();
                throw new Exception('Queue not found or not assigned to this counter');
            }
            $verifyStmt->close();
            
            $stallStmt = $conn->prepare("
                UPDATE queues 
                SET status = 'stalled',
                    updated_at = NOW(),
                    worker_id = ?
                WHERE id = ?
            ");
            $stallStmt->bind_param("si", $studentId, $queueId);
            $stallStmt->execute();
            $stallStmt->close();
            
            logQueueAction($conn, $queueId, 'stalled', "Stalled by worker at counter $counterNumber");
            
            // Get next queue that matches this counter's type
            $nextQueue = getNextQueueForCounter($conn, $counterNumber);
            if ($nextQueue) {
                $nextStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        counter_id = ?,
                        window_number = ?,
                        served_at = NOW(),
                        updated_at = NOW(),
                        worker_id = ?
                    WHERE id = ?
                ");
                $nextStmt->bind_param("iisi", $counterNumber, $counterNumber, $studentId, $nextQueue['id']);
                $nextStmt->execute();
                $nextStmt->close();
                
                logQueueAction($conn, $nextQueue['id'], 'serving', "Now being served at counter $counterNumber");
            }
            
            echo json_encode(['success' => true, 'message' => 'Queue stalled']);
            break;
            
        case 'skip':
            // Skip queue
            $queueId = $_POST['queue_id'] ?? null;
            
            if (!$queueId) {
                throw new Exception('Queue ID is required');
            }
            
            // Verify this queue belongs to this counter
            $verifyStmt = $conn->prepare("
                SELECT id, counter_id FROM queues 
                WHERE id = ? AND counter_id = ?
            ");
            $verifyStmt->bind_param("ii", $queueId, $counterNumber);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult->num_rows === 0) {
                $verifyStmt->close();
                throw new Exception('Queue not found or not assigned to this counter');
            }
            $verifyStmt->close();
            
            $skipStmt = $conn->prepare("
                UPDATE queues 
                SET status = 'skipped',
                    updated_at = NOW(),
                    worker_id = ?
                WHERE id = ?
            ");
            $skipStmt->bind_param("si", $studentId, $queueId);
            $skipStmt->execute();
            $skipStmt->close();
            
            logQueueAction($conn, $queueId, 'skipped', "Skipped by worker at counter $counterNumber");
            
            // Get next queue that matches this counter's type
            $nextQueue = getNextQueueForCounter($conn, $counterNumber);
            if ($nextQueue) {
                $nextStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'serving',
                        counter_id = ?,
                        window_number = ?,
                        served_at = NOW(),
                        updated_at = NOW(),
                        worker_id = ?
                    WHERE id = ?
                ");
                $nextStmt->bind_param("iisi", $counterNumber, $counterNumber, $studentId, $nextQueue['id']);
                $nextStmt->execute();
                $nextStmt->close();
                
                logQueueAction($conn, $nextQueue['id'], 'serving', "Now being served at counter $counterNumber");
            }
            
            echo json_encode(['success' => true, 'message' => 'Queue skipped']);
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

