<?php
// Admin Queue Functions

// Get total queues for today
function getTodayTotalQueues($conn) {
    try {
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM queues 
            WHERE DATE(created_at) = ?
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting total queues: " . $e->getMessage());
        return 0;
    }
}

// Get waiting queues (status = 'waiting')
function getWaitingQueues($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM queues 
            WHERE status = 'waiting'
            AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting waiting queues: " . $e->getMessage());
        return 0;
    }
}

// Get currently serving queue (status = 'serving') - UPDATED to include ID
function getCurrentlyServing($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT id, queue_number, student_name, queue_type, window_number 
            FROM queues 
            WHERE status = 'serving'
            AND DATE(created_at) = CURDATE()
            ORDER BY updated_at DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting currently serving: " . $e->getMessage());
        return [];
    }
}

// Get pending queues count (status = 'waiting')
function getPendingQueuesCount($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM queues 
            WHERE status = 'waiting'
            AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting pending queues: " . $e->getMessage());
        return 0;
    }
}

// Get completed queues count (status = 'completed')
function getCompletedQueuesCount($conn) {
    try {
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM queues 
            WHERE status = 'completed'
            AND DATE(created_at) = ?
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting completed queues: " . $e->getMessage());
        return 0;
    }
}

// Get priority queues count
function getPriorityQueuesCount($conn) {
    try {
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM queues 
            WHERE queue_type = 'priority'
            AND DATE(created_at) = ?
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting priority queues: " . $e->getMessage());
        return 0;
    }
}

// Get regular queues count
function getRegularQueuesCount($conn) {
    try {
        $today = date('Y-m-d');
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM queues 
            WHERE queue_type = 'regular'
            AND DATE(created_at) = ?
        ");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error getting regular queues: " . $e->getMessage());
        return 0;
    }
}

// Get all waiting queues with details
function getWaitingQueuesList($conn, $limit = 10) {
    try {
        $stmt = $conn->prepare("
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
                q.status
            FROM queues q
            WHERE q.status = 'waiting'
            AND DATE(q.created_at) = CURDATE()
            ORDER BY 
                CASE WHEN q.queue_type = 'priority' THEN 0 ELSE 1 END,
                q.created_at ASC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting waiting queues list: " . $e->getMessage());
        return [];
    }
}

// Get queue details by ID
function getQueueDetails($conn, $queueId) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                q.*,
                GROUP_CONCAT(qs.service_name SEPARATOR ', ') as services
            FROM queues q
            LEFT JOIN queue_services qs ON q.id = qs.queue_id
            WHERE q.id = ?
            GROUP BY q.id
        ");
        $stmt->bind_param("i", $queueId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    } catch (Exception $e) {
        error_log("Error getting queue details: " . $e->getMessage());
        return null;
    }
}

// Get average waiting time (in minutes)
function getAverageWaitingTime($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                AVG(TIMESTAMPDIFF(MINUTE, created_at, served_at)) as avg_wait
            FROM queues
            WHERE status = 'completed'
            AND DATE(created_at) = CURDATE()
            AND served_at IS NOT NULL
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return round($row['avg_wait'] ?? 0, 1);
    } catch (Exception $e) {
        error_log("Error getting average waiting time: " . $e->getMessage());
        return 0;
    }
}

// Get queue statistics for dashboard
function getQueueStatistics($conn) {
    return [
        'total_today' => getTodayTotalQueues($conn),
        'waiting' => getWaitingQueues($conn),
        'serving' => count(getCurrentlyServing($conn)),
        'pending' => getPendingQueuesCount($conn),
        'completed' => getCompletedQueuesCount($conn),
        'priority' => getPriorityQueuesCount($conn),
        'regular' => getRegularQueuesCount($conn),
        'avg_wait_time' => getAverageWaitingTime($conn)
    ];
}

// Update queue status - REMOVED (no longer needed, handled in queue_actions.php directly)

// Get next queue to serve (priority first, then regular by time)
function getNextQueueToServe($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                id,
                queue_number,
                queue_type,
                student_name,
                services_count
            FROM queues
            WHERE status = 'waiting'
            AND DATE(created_at) = CURDATE()
            ORDER BY 
                CASE WHEN queue_type = 'priority' THEN 0 ELSE 1 END,
                created_at ASC
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    } catch (Exception $e) {
        error_log("Error getting next queue: " . $e->getMessage());
        return null;
    }
}

// Assign a counter to a worker (for 'Working' role users)
// Returns counter number (1-4) on success, false if all counters are occupied
function assignCounterToWorker($conn, $studentId) {
    try {
        // First, check if this worker already has an active counter assignment
        $checkStmt = $conn->prepare("
            SELECT counter_number 
            FROM counter_assignments 
            WHERE student_id = ? AND is_active = 1 
            LIMIT 1
        ");
        $checkStmt->bind_param("i", $studentId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult && $checkResult->num_rows > 0) {
            // Worker already has an active counter assignment
            $row = $checkResult->fetch_assoc();
            $checkStmt->close();
            return (int)$row['counter_number'];
        }
        $checkStmt->close();
        
        // Find an available counter (1-4) that doesn't have an active assignment
        // Counters 1-3 are regular, Counter 4 is priority
        for ($counterNum = 1; $counterNum <= 4; $counterNum++) {
            $availableStmt = $conn->prepare("
                SELECT id 
                FROM counter_assignments 
                WHERE counter_number = ? AND is_active = 1 
                LIMIT 1
            ");
            $availableStmt->bind_param("i", $counterNum);
            $availableStmt->execute();
            $availableResult = $availableStmt->get_result();
            
            // If no active assignment found for this counter, assign it
            if ($availableResult && $availableResult->num_rows === 0) {
                $availableStmt->close();
                
                // Create new counter assignment
                $assignStmt = $conn->prepare("
                    INSERT INTO counter_assignments (student_id, counter_number, assigned_at, is_active)
                    VALUES (?, ?, NOW(), 1)
                ");
                $assignStmt->bind_param("ii", $studentId, $counterNum);
                
                if ($assignStmt->execute()) {
                    $assignStmt->close();
                    return $counterNum;
                } else {
                    $assignStmt->close();
                    // Continue to next counter if insert failed
                }
            } else {
                $availableStmt->close();
            }
        }
        
        // All counters are occupied
        return false;
        
    } catch (Exception $e) {
        error_log("Error assigning counter to worker: " . $e->getMessage());
        return false;
    }
}

// Release counter assignment when worker logs out
function releaseCounterAssignment($conn, $studentId) {
    try {
        // Release any active counter assignments for this student
        $releaseStmt = $conn->prepare("
            UPDATE counter_assignments 
            SET is_active = 0, released_at = NOW()
            WHERE student_id = ? AND is_active = 1
        ");
        
        // Handle both string and integer student IDs
        if (is_numeric($studentId)) {
            $studentIdInt = (int)$studentId;
            $releaseStmt->bind_param("i", $studentIdInt);
        } else {
            $releaseStmt->bind_param("s", $studentId);
        }
        
        $releaseStmt->execute();
        $releaseStmt->close();
        
        return true;
    } catch (Exception $e) {
        error_log("Error releasing counter assignment: " . $e->getMessage());
        return false;
    }
}

// Get next queue for a specific counter based on counter type
// Counters 1-3 handle regular queues, Counter 4 handles priority queues
function getNextQueueForCounter($conn, $counterNumber) {
    try {
        // Determine counter type: counters 1-3 are regular, counter 4 is priority
        $counterType = ($counterNumber == 4) ? 'priority' : 'regular';
        
        // Get the next waiting queue that matches this counter's type
        // Priority queues go first, then regular queues by creation time
        $stmt = $conn->prepare("
            SELECT 
                id,
                queue_number,
                queue_type,
                student_name,
                student_id,
                services_count
            FROM queues
            WHERE status = 'waiting'
            AND queue_type = ?
            AND DATE(created_at) = CURDATE()
            ORDER BY created_at ASC
            LIMIT 1
        ");
        
        $stmt->bind_param("s", $counterType);
        $stmt->execute();
        $result = $stmt->get_result();
        $queue = $result->fetch_assoc();
        $stmt->close();
        
        return $queue ? $queue : null;
    } catch (Exception $e) {
        error_log("Error getting next queue for counter: " . $e->getMessage());
        return null;
    }
}
?>