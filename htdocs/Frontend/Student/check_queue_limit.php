<?php
/**
 * check_queue_limit.php
 * 
 * Functions to check and enforce queue capacity limits
 * Include this in your queue creation files
 */

require_once __DIR__ . '/db_config.php';

/**
 * Check if queue system has reached capacity
 * 
 * @param mysqli $conn Database connection
 * @return array ['canQueue' => bool, 'current' => int, 'max' => int, 'message' => string]
 */
function checkQueueCapacity($conn) {
    try {
        // Get max capacity setting
        $maxCapacity = getMaxQueueCapacity($conn);
        
        // Count active queues (waiting + serving)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as active_count
            FROM queues
            WHERE status IN ('waiting', 'serving')
            AND DATE(created_at) = CURDATE()
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $activeCount = (int)$row['active_count'];
        $stmt->close();
        
        $canQueue = $activeCount < $maxCapacity;
        
        return [
            'canQueue' => $canQueue,
            'current' => $activeCount,
            'max' => $maxCapacity,
            'remaining' => max(0, $maxCapacity - $activeCount),
            'message' => $canQueue 
                ? "Queue available ({$activeCount}/{$maxCapacity})" 
                : "Queue is full ({$activeCount}/{$maxCapacity}). Please try again later."
        ];
        
    } catch (Exception $e) {
        error_log("Error checking queue capacity: " . $e->getMessage());
        
        // If error, allow queueing (fail-open)
        return [
            'canQueue' => true,
            'current' => 0,
            'max' => 100,
            'remaining' => 100,
            'message' => 'Queue capacity check unavailable'
        ];
    }
}

/**
 * Get maximum queue capacity from settings
 * 
 * @param mysqli $conn Database connection
 * @return int Maximum capacity (default: 100)
 */
function getMaxQueueCapacity($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT setting_value 
            FROM system_settings 
            WHERE setting_key = 'maxQueueCapacity'
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $capacity = (int)$row['setting_value'];
            $stmt->close();
            return max(1, min(500, $capacity)); // Enforce min 1, max 500
        }
        
        $stmt->close();
        return 100; // Default
        
    } catch (Exception $e) {
        error_log("Error getting max queue capacity: " . $e->getMessage());
        return 100; // Default on error
    }
}

/**
 * Get queue statistics including capacity info
 * 
 * @param mysqli $conn Database connection
 * @return array Statistics including capacity usage
 */
function getQueueCapacityStats($conn) {
    $capacityInfo = checkQueueCapacity($conn);
    
    return [
        'current_active' => $capacityInfo['current'],
        'max_capacity' => $capacityInfo['max'],
        'remaining_slots' => $capacityInfo['remaining'],
        'utilization_percentage' => round(($capacityInfo['current'] / $capacityInfo['max']) * 100, 1),
        'is_full' => !$capacityInfo['canQueue']
    ];
}

/**
 * Display queue capacity warning/error message
 * 
 * @param array $capacityInfo Result from checkQueueCapacity()
 * @return string HTML for capacity message
 */
function getCapacityAlertHTML($capacityInfo) {
    $percentage = ($capacityInfo['current'] / $capacityInfo['max']) * 100;
    
    if (!$capacityInfo['canQueue']) {
        // Full capacity - Error
        return '
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
            <div class="flex">
                <div class="py-1"><i class="fas fa-exclamation-circle mr-2"></i></div>
                <div>
                    <p class="font-bold">Queue System Full</p>
                    <p class="text-sm">The queue has reached maximum capacity (' . $capacityInfo['current'] . '/' . $capacityInfo['max'] . '). Please try again later.</p>
                </div>
            </div>
        </div>';
    } elseif ($percentage >= 90) {
        // Near capacity - Warning
        return '
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded" role="alert">
            <div class="flex">
                <div class="py-1"><i class="fas fa-exclamation-triangle mr-2"></i></div>
                <div>
                    <p class="font-bold">Queue Almost Full</p>
                    <p class="text-sm">Only ' . $capacityInfo['remaining'] . ' slot(s) remaining. Queue quickly!</p>
                </div>
            </div>
        </div>';
    } elseif ($percentage >= 75) {
        // Moderate capacity - Info
        return '
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded" role="alert">
            <div class="flex">
                <div class="py-1"><i class="fas fa-info-circle mr-2"></i></div>
                <div>
                    <p class="font-bold">High Queue Volume</p>
                    <p class="text-sm">' . $capacityInfo['remaining'] . ' slot(s) remaining. Consider queueing soon.</p>
                </div>
            </div>
        </div>';
    }
    
    return ''; // No message needed
}
?>