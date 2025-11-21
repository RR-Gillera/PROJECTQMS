<?php
// Logout handler - clears session and redirects to login
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Release counter assignment if user is a worker and clear LastActivity on logout
if (isset($_SESSION['user']) && isset($_SESSION['user']['studentId'])) {
    $studentId = $_SESSION['user']['studentId'];
    $role = $_SESSION['user']['role'] ?? '';
    
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/admin_functions.php';
    
    // Check if database connection is available
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_errno) {
        try {
            // Clear LastActivity to mark user as inactive/offline
            $studentIdInt = (int)$studentId;
            $clearActivityStmt = $conn->prepare('UPDATE Accounts SET LastActivity = NULL WHERE StudentID = ?');
            if ($clearActivityStmt) {
                $clearActivityStmt->bind_param('i', $studentIdInt);
                $clearActivityStmt->execute();
                $clearActivityStmt->close();
            }
            
            // Release counter assignment for both Admin and Working roles
            if (strtolower($role) === 'working' || strtolower($role) === 'admin') {
                // Release counter assignment - pass integer StudentID
                releaseCounterAssignment($conn, $studentIdInt);
            }
        } catch (Exception $e) {
            // Log error but continue with logout process
            error_log("Logout error: " . $e->getMessage());
        }
    } else {
        // Log database connection issue but continue with logout
        error_log("Logout: Database connection not available, skipping database cleanup");
    }
}

// Get session ID before destroying
$sessionId = session_id();
$sessionPath = session_save_path();
if (empty($sessionPath)) {
    $sessionPath = sys_get_temp_dir();
}

// Clear all session data
$_SESSION = array();

// Destroy the session cookie immediately
$sessionName = session_name();
$cookieParams = session_get_cookie_params();
if (isset($_COOKIE[$sessionName])) {
    setcookie($sessionName, '', time() - 3600, 
        $cookieParams['path'], 
        $cookieParams['domain'], 
        $cookieParams['secure'], 
        $cookieParams['httponly']
    );
}

// Destroy the session
session_destroy();

// Explicitly delete the session file if it still exists
if ($sessionId) {
    $sessionFile = rtrim($sessionPath, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
    if (file_exists($sessionFile)) {
        @unlink($sessionFile);
    }
}

// Redirect to login page
header('Location: Signin.php');
exit;
?>