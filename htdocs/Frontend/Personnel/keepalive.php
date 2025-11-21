<?php
// Keep-alive endpoint to update LastActivity for active users
header('Content-Type: application/json; charset=utf-8');

// Get session ID from cookie BEFORE starting session to check if it exists
$sessionName = session_name();
$sessionIdFromCookie = isset($_COOKIE[$sessionName]) ? $_COOKIE[$sessionName] : null;

// If there's no session cookie at all, user is not logged in
if (!$sessionIdFromCookie || empty($sessionIdFromCookie)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No session cookie']);
    exit;
}

// If there's a session cookie, check if the session file exists and is not empty BEFORE starting session
if ($sessionIdFromCookie) {
    $sessionPath = session_save_path();
    if (empty($sessionPath)) {
        $sessionPath = sys_get_temp_dir();
    }
    $sessionFile = rtrim($sessionPath, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sessionIdFromCookie;
    
    // If session file doesn't exist, session was destroyed (logout)
    if (!file_exists($sessionFile)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session destroyed']);
        exit;
    }
    
    // Check if session file is empty (session was cleared but file not deleted yet)
    if (filesize($sessionFile) === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session cleared']);
        exit;
    }
}

// Now start the session
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Check if user is logged in - strict validation
if (!isset($_SESSION['user']) || 
    !is_array($_SESSION['user']) || 
    !isset($_SESSION['user']['studentId']) || 
    empty($_SESSION['user']['studentId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Additional check: verify session is not empty/destroyed
if (empty($_SESSION) || session_status() !== PHP_SESSION_ACTIVE) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session expired']);
    exit;
}

require_once __DIR__ . '/../../config.php';

$studentIdSession = $_SESSION['user']['studentId'];
// Try both integer and string formats to handle type mismatches
$studentIdInt = (int)$studentIdSession;
$studentIdStr = (string)$studentIdSession;

// First, ensure LastActivity column exists
$checkColumn = $conn->query("SHOW COLUMNS FROM Accounts LIKE 'LastActivity'");
if (!$checkColumn || $checkColumn->num_rows === 0) {
    // Create the column if it doesn't exist
    $conn->query("ALTER TABLE Accounts ADD COLUMN LastActivity DATETIME NULL DEFAULT NULL");
}

// Update LastActivity timestamp using PHP's current time (respects timezone)
// This ensures the timezone is correct regardless of MySQL server settings
$currentDateTime = date('Y-m-d H:i:s');

// Update LastActivity timestamp - try integer first, then string if that fails
$stmt = $conn->prepare('UPDATE Accounts SET LastActivity = ? WHERE StudentID = ?');
if ($stmt) {
    $stmt->bind_param('si', $currentDateTime, $studentIdInt);
    $success = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    // If no rows were affected, try with string format
    if (!$success || $affectedRows === 0) {
        $stmt2 = $conn->prepare('UPDATE Accounts SET LastActivity = ? WHERE StudentID = ?');
        if ($stmt2) {
            $stmt2->bind_param('ss', $currentDateTime, $studentIdStr);
            $success2 = $stmt2->execute();
            $stmt2->close();
            
            if ($success2) {
                echo json_encode(['success' => true, 'message' => 'Activity updated']);
            } else {
                http_response_code(500);
                log_error_to_file('Update failed for StudentID (string): ' . $studentIdStr);
                echo json_encode(['success' => false, 'error' => 'Update failed']);
            }
        } else {
            http_response_code(500);
            log_error_to_file('Database error: ' . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'Activity updated']);
    }
} else {
    http_response_code(500);
    log_error_to_file('Database error: ' . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}

// Improvement: Log errors to a file for audit
function log_error_to_file($message) {
    $logFile = __DIR__ . '/../../logs/keepalive_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}
?>

