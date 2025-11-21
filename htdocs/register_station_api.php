<?php
/**
 * Public Station Registration
 * Allows initial PC registration without authentication
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1); // Show errors for debugging
ini_set('log_errors', 1);

// Try to include config from the correct path
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // Output error as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Configuration file not found at: ' . __DIR__ . '/config.php'
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action !== 'register_station') {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stationName = trim($_POST['station_name'] ?? '');
    $macAddress = strtoupper(trim($_POST['mac_address'] ?? ''));
    $counterNumber = intval($_POST['counter_number'] ?? 0);
    $ipAddress = trim($_POST['ip_address'] ?? '');
    
    // Validate inputs
    if (empty($stationName)) {
        throw new Exception('Station name is required');
    }
    
    if (empty($macAddress)) {
        throw new Exception('MAC address is required');
    }
    
    if ($counterNumber < 1 || $counterNumber > 4) {
        throw new Exception('Counter number must be between 1 and 4');
    }
    
    // Validate MAC address format (XX-XX-XX-XX-XX-XX or XX:XX:XX:XX:XX:XX)
    $macAddress = str_replace(':', '-', $macAddress);
    if (!preg_match('/^([0-9A-F]{2}-){5}[0-9A-F]{2}$/', $macAddress)) {
        throw new Exception('Invalid MAC address format. Use XX-XX-XX-XX-XX-XX');
    }
    
    // Check if MAC address already exists
    $checkStmt = $conn->prepare("SELECT id, station_name, counter_number FROM station_assignments WHERE mac_address = ?");
    $checkStmt->bind_param("s", $macAddress);
    $checkStmt->execute();
    $existingResult = $checkStmt->get_result();
    
    if ($existingResult->num_rows > 0) {
        $existing = $existingResult->fetch_assoc();
        throw new Exception("This MAC address is already registered as '{$existing['station_name']}' for Counter {$existing['counter_number']}");
    }
    $checkStmt->close();
    
    // Deactivate other stations for this counter (only one active station per counter)
    $deactivateStmt = $conn->prepare("UPDATE station_assignments SET is_active = 0 WHERE counter_number = ? AND is_active = 1");
    $deactivateStmt->bind_param("i", $counterNumber);
    $deactivateStmt->execute();
    $deactivateStmt->close();
    
    // Insert new station with all columns
    $insertStmt = $conn->prepare("
        INSERT INTO station_assignments (station_name, mac_address, counter_number, ip_address, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, 1, NOW(), NOW())
    ");
    $insertStmt->bind_param("ssis", $stationName, $macAddress, $counterNumber, $ipAddress);
    
    if ($insertStmt->execute()) {
        $stationId = $conn->insert_id;
        $insertStmt->close();
        $conn->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Station registered successfully',
            'station_id' => $stationId,
            'station_name' => $stationName,
            'mac_address' => $macAddress,
            'counter_number' => $counterNumber
        ]);
    } else {
        throw new Exception('Failed to insert station record: ' . $conn->error);
    }
    
} catch (Exception $e) {
    http_response_code(200); // Force 200 so JSON can be parsed
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
