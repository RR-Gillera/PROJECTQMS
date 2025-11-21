<?php
/**
 * auto_cancel_trigger.php
 * 
 * Alternative to cron jobs - runs automatically when included in pages
 * Add this to your frequently visited pages (Dashboard, Queue, QueueRequest)
 * 
 * INCLUDE THIS LINE at the top of your admin pages:
 * include_once 'auto_cancel_trigger.php';
 */

// Only run if not already run in the last minute
$lastRunKey = 'last_auto_cancel_run';
$currentTime = time();
$lastRunTime = isset($_SESSION[$lastRunKey]) ? $_SESSION[$lastRunKey] : 0;

// Run every 1 minute (60 seconds)
$runInterval = 60;

if (($currentTime - $lastRunTime) >= $runInterval) {
    // Update last run time immediately to prevent multiple simultaneous runs
    $_SESSION[$lastRunKey] = $currentTime;
    
    try {
        require_once __DIR__ . '/../../Student/db_config.php';
        
        // Get skip timeout settings
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE setting_key IN ('skipTimeout', 'skipTimeoutUnit')
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $settings = [
            'skipTimeout' => 1,
            'skipTimeoutUnit' => 'hours'
        ];
        
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $stmt->close();
        
        $timeoutValue = (int)$settings['skipTimeout'];
        $timeoutUnit = $settings['skipTimeoutUnit'];
        
        // Calculate timeout in minutes
        $timeoutMinutes = ($timeoutUnit === 'hours') ? ($timeoutValue * 60) : $timeoutValue;
        
        // Find skipped queues that have exceeded timeout
        $stmt = $conn->prepare("
            SELECT id, queue_number, student_name, updated_at
            FROM queues
            WHERE status = 'skipped'
            AND DATE(created_at) = CURDATE()
            AND TIMESTAMPDIFF(MINUTE, updated_at, NOW()) >= ?
        ");
        
        $stmt->bind_param("i", $timeoutMinutes);
        $stmt->execute();
        $result = $stmt->get_result();
        $expiredQueues = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (!empty($expiredQueues)) {
            $cancelledCount = 0;
            
            foreach ($expiredQueues as $queue) {
                $updateStmt = $conn->prepare("
                    UPDATE queues 
                    SET status = 'cancelled',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $updateStmt->bind_param("i", $queue['id']);
                
                if ($updateStmt->execute()) {
                    $cancelledCount++;
                    
                    // Log the auto-cancellation
                    $logStmt = $conn->prepare("
                        INSERT INTO queue_logs (queue_id, action, description, created_at)
                        VALUES (?, 'auto_cancelled', ?, NOW())
                    ");
                    
                    $description = "Queue auto-cancelled after {$timeoutValue} {$timeoutUnit} of being skipped";
                    $logStmt->bind_param("is", $queue['id'], $description);
                    $logStmt->execute();
                    $logStmt->close();
                    
                    error_log("Auto-cancelled queue: {$queue['queue_number']} ({$queue['student_name']})");
                }
                
                $updateStmt->close();
            }
            
            error_log("Auto-cancel completed: {$cancelledCount} queue(s) cancelled");
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Auto-cancel trigger error: " . $e->getMessage());
    }
}
?>