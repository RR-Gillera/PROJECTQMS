<?php
// Database Configuration File
// Create this file in your project root directory

// Database credentials
define('DB_HOST', 'sql204.byethost5.com');
define('DB_USER', 'b5_40277518');
define('DB_PASS', 'euwen123');
define('DB_NAME', 'b5_40277518_QMS');

// Create database connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset to utf8mb4
        $conn->set_charset("utf8mb4");
        
        // Try to set timezone to Manila, but continue even if it fails
        try {
            $conn->query("SET time_zone = '+08:00'");
        } catch (Exception $e) {
            error_log("Warning: Could not set timezone: " . $e->getMessage());
            // Continue without timezone setting
        }
        
        return $conn;
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection failed. Please try again later.");
    }
}

// Function to generate next queue number
function generateQueueNumber($conn, $queueType) {
    // Check if transaction is supported (InnoDB required)
    $transactionSupported = true;
    try {
        $conn->begin_transaction();
    } catch (Exception $e) {
        $transactionSupported = false;
        error_log("Transactions not supported, proceeding without transaction: " . $e->getMessage());
    }
    
    try {
        // Check if queue_counters table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'queue_counters'");
        if ($tableCheck->num_rows === 0) {
            throw new Exception("Database table 'queue_counters' does not exist. Please create the table first.");
        }
        
        // Check if today's date is different from last reset date
        $checkStmt = $conn->prepare("SELECT current_number, last_reset_date FROM queue_counters WHERE queue_type = ?");
        if (!$checkStmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        $checkStmt->bind_param("s", $queueType);
        if (!$checkStmt->execute()) {
            throw new Exception("Failed to execute query: " . $checkStmt->error);
        }
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            // If counter doesn't exist, create it
            $insertStmt = $conn->prepare("INSERT INTO queue_counters (queue_type, current_number, last_reset_date) VALUES (?, 0, CURDATE())");
            if (!$insertStmt) {
                throw new Exception("Failed to prepare insert statement: " . $conn->error);
            }
            $insertStmt->bind_param("s", $queueType);
            if (!$insertStmt->execute()) {
                throw new Exception("Failed to insert queue counter: " . $insertStmt->error);
            }
            $insertStmt->close();
            
            // Re-query to get the newly inserted row
            $checkStmt = $conn->prepare("SELECT current_number, last_reset_date FROM queue_counters WHERE queue_type = ?");
            if (!$checkStmt) {
                throw new Exception("Failed to prepare re-query statement: " . $conn->error);
            }
            $checkStmt->bind_param("s", $queueType);
            if (!$checkStmt->execute()) {
                throw new Exception("Failed to execute re-query: " . $checkStmt->error);
            }
            $checkResult = $checkStmt->get_result();
        }
        
        $checkRow = $checkResult->fetch_assoc();
        if (!$checkRow) {
            throw new Exception("Failed to fetch queue counter data");
        }
        $checkStmt->close();
        
        // Check if we need to reset the counter (new day)
        $today = date('Y-m-d');
        if ($checkRow['last_reset_date'] !== $today) {
            // Reset counter for new day
            $resetStmt = $conn->prepare("UPDATE queue_counters SET current_number = 0, last_reset_date = ? WHERE queue_type = ?");
            if (!$resetStmt) {
                throw new Exception("Failed to prepare reset statement: " . $conn->error);
            }
            $resetStmt->bind_param("ss", $today, $queueType);
            if (!$resetStmt->execute()) {
                throw new Exception("Failed to reset queue counter: " . $resetStmt->error);
            }
            $resetStmt->close();
            
            $currentNumber = 0;
        } else {
            $currentNumber = $checkRow['current_number'];
        }
        
        // Increment the counter
        $nextNumber = $currentNumber + 1;
        
        // Update the counter with FOR UPDATE lock to prevent race conditions
        $updateStmt = $conn->prepare("UPDATE queue_counters SET current_number = ?, updated_at = CURRENT_TIMESTAMP WHERE queue_type = ?");
        $updateStmt->bind_param("is", $nextNumber, $queueType);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update queue counter: " . $updateStmt->error);
        }
        
        $updateStmt->close();
        
        // Generate queue number string
        $prefix = ($queueType === 'priority') ? 'P' : 'R';
        $queueNumber = $prefix . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        
        // Verify the queue number doesn't already exist (extra safety)
        $verifyStmt = $conn->prepare("SELECT id FROM queues WHERE queue_number = ? AND DATE(created_at) = CURDATE()");
        if (!$verifyStmt) {
            // If queues table doesn't exist, skip verification
            error_log("Warning: queues table might not exist, skipping verification");
        } else {
            $verifyStmt->bind_param("s", $queueNumber);
            if ($verifyStmt->execute()) {
                $verifyResult = $verifyStmt->get_result();
                
                if ($verifyResult && $verifyResult->num_rows > 0) {
                    // Queue number exists, try again with incremented number
                    $verifyStmt->close();
                    if ($transactionSupported) {
                        $conn->rollback();
                    }
                    throw new Exception("Queue number collision detected. Please try again.");
                }
            }
            $verifyStmt->close();
        }
        
        if ($transactionSupported) {
            if (!$conn->commit()) {
                throw new Exception("Failed to commit transaction: " . $conn->error);
            }
        }
        
        error_log("Generated queue number: $queueNumber for type: $queueType");
        
        return $queueNumber;
        
    } catch (Exception $e) {
        if ($transactionSupported) {
            try {
                $conn->rollback();
            } catch (Exception $rollbackError) {
                error_log("Error during rollback: " . $rollbackError->getMessage());
            }
        }
        error_log("Error generating queue number: " . $e->getMessage() . " | Queue Type: " . $queueType);
        error_log("PHP Error Details: " . print_r(error_get_last(), true));
        throw $e;
    }
}

// Function to insert queue record
function insertQueueRecord($conn, $queueData) {
    try {
        // Check if queues table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'queues'");
        if ($tableCheck->num_rows === 0) {
            throw new Exception("Database table 'queues' does not exist. Please create the table first.");
        }
        
        $stmt = $conn->prepare("
            INSERT INTO queues (
                queue_number, queue_type, student_name, student_id, 
                year_level, course_program, services_count, has_qr, qr_code_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param(
            "ssssssiis",
            $queueData['queue_number'],
            $queueData['queue_type'],
            $queueData['student_name'],
            $queueData['student_id'],
            $queueData['year_level'],
            $queueData['course_program'],
            $queueData['services_count'],
            $queueData['has_qr'],
            $queueData['qr_code_url']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $insertId = $conn->insert_id;
        $stmt->close();
        
        error_log("Inserted queue record with ID: $insertId");
        
        return $insertId;
        
    } catch (Exception $e) {
        error_log("Error inserting queue record: " . $e->getMessage());
        throw $e;
    }
}

// Function to insert queue services
function insertQueueServices($conn, $queueId, $services) {
    try {
        $stmt = $conn->prepare("INSERT INTO queue_services (queue_id, service_name) VALUES (?, ?)");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        foreach ($services as $service) {
            $stmt->bind_param("is", $queueId, $service);
            if (!$stmt->execute()) {
                error_log("Failed to insert service '$service': " . $stmt->error);
            }
        }
        
        $stmt->close();
        return true;
    } catch (Exception $e) {
        error_log("Error inserting queue services: " . $e->getMessage());
        throw $e;
    }
}

// Function to log queue action
function logQueueAction($conn, $queueId, $action, $description = '') {
    try {
        $stmt = $conn->prepare("INSERT INTO queue_logs (queue_id, action, description) VALUES (?, ?, ?)");
        
        if (!$stmt) {
            error_log("Prepare failed for queue log: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("iss", $queueId, $action, $description);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error logging queue action: " . $e->getMessage());
        return false;
    }
}

// Function to reset daily counters (optional - call this daily via cron job or automatically)
function resetDailyCounters($conn) {
    try {
        $today = date('Y-m-d');
        
        $stmt = $conn->prepare("
            UPDATE queue_counters 
            SET current_number = 0, last_reset_date = ? 
            WHERE last_reset_date < ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $today, $today);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error resetting counters: " . $e->getMessage());
        return false;
    }
}

// Function to get current queue statistics
function getQueueStats($conn, $queueType = null) {
    try {
        if ($queueType) {
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
                    SUM(CASE WHEN status = 'serving' THEN 1 ELSE 0 END) as serving,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM queues 
                WHERE queue_type = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->bind_param("s", $queueType);
        } else {
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
                    SUM(CASE WHEN status = 'serving' THEN 1 ELSE 0 END) as serving,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM queues 
                WHERE DATE(created_at) = CURDATE()
            ");
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting queue stats: " . $e->getMessage());
        return null;
    }
}
?>