<?php
/**
 * Station Management API
 * Handles station registration, MAC address detection, and counter assignment
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $conn = getDBConnection();
    
    switch ($action) {
        case 'get_stations':
            // Get all registered stations
            $stmt = $conn->prepare("
                SELECT 
                    s.*,
                    a.FullName as created_by_name
                FROM station_assignments s
                LEFT JOIN Accounts a ON s.created_by = a.ID
                ORDER BY s.counter_number ASC, s.created_at DESC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $stations = [];
            while ($row = $result->fetch_assoc()) {
                $stations[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'stations' => $stations
            ]);
            break;
            
        case 'register_station':
            // Register a new station
            $stationName = trim($_POST['station_name'] ?? '');
            $macAddress = strtoupper(trim($_POST['mac_address'] ?? ''));
            $counterNumber = intval($_POST['counter_number'] ?? 0);
            $ipAddress = trim($_POST['ip_address'] ?? '');
            
            // Validate inputs
            if (empty($stationName) || empty($macAddress) || $counterNumber < 1 || $counterNumber > 4) {
                throw new Exception('Invalid input data');
            }
            
            // Validate MAC address format (XX-XX-XX-XX-XX-XX or XX:XX:XX:XX:XX:XX)
            $macAddress = str_replace(':', '-', $macAddress);
            if (!preg_match('/^([0-9A-F]{2}-){5}[0-9A-F]{2}$/', $macAddress)) {
                throw new Exception('Invalid MAC address format. Use XX-XX-XX-XX-XX-XX format');
            }
            
            // Check if MAC address already exists
            $checkStmt = $conn->prepare("SELECT id FROM station_assignments WHERE mac_address = ?");
            $checkStmt->bind_param("s", $macAddress);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                throw new Exception('This MAC address is already registered');
            }
            
            // Deactivate other stations for this counter
            $deactivateStmt = $conn->prepare("UPDATE station_assignments SET is_active = 0 WHERE counter_number = ? AND is_active = 1");
            $deactivateStmt->bind_param("i", $counterNumber);
            $deactivateStmt->execute();
            
            // Insert new station
            $adminId = $_SESSION['user']['id'];
            $insertStmt = $conn->prepare("
                INSERT INTO station_assignments (station_name, mac_address, counter_number, ip_address, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->bind_param("ssisi", $stationName, $macAddress, $counterNumber, $ipAddress, $adminId);
            
            if ($insertStmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Station registered successfully',
                    'station_id' => $conn->insert_id
                ]);
            } else {
                throw new Exception('Failed to register station');
            }
            break;
            
        case 'delete_station':
            // Delete a station
            $stationId = intval($_POST['station_id'] ?? 0);
            
            if ($stationId <= 0) {
                throw new Exception('Invalid station ID');
            }
            
            $deleteStmt = $conn->prepare("DELETE FROM station_assignments WHERE id = ?");
            $deleteStmt->bind_param("i", $stationId);
            
            if ($deleteStmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Station deleted successfully'
                ]);
            } else {
                throw new Exception('Failed to delete station');
            }
            break;
            
        case 'toggle_station':
            // Activate/deactivate a station
            $stationId = intval($_POST['station_id'] ?? 0);
            $isActive = intval($_POST['is_active'] ?? 0);
            
            if ($stationId <= 0) {
                throw new Exception('Invalid station ID');
            }
            
            // If activating, deactivate other stations for this counter
            if ($isActive) {
                $getCounterStmt = $conn->prepare("SELECT counter_number FROM station_assignments WHERE id = ?");
                $getCounterStmt->bind_param("i", $stationId);
                $getCounterStmt->execute();
                $counterResult = $getCounterStmt->get_result();
                $counterRow = $counterResult->fetch_assoc();
                
                if ($counterRow) {
                    $deactivateStmt = $conn->prepare("UPDATE station_assignments SET is_active = 0 WHERE counter_number = ? AND id != ?");
                    $deactivateStmt->bind_param("ii", $counterRow['counter_number'], $stationId);
                    $deactivateStmt->execute();
                }
            }
            
            $toggleStmt = $conn->prepare("UPDATE station_assignments SET is_active = ? WHERE id = ?");
            $toggleStmt->bind_param("ii", $isActive, $stationId);
            
            if ($toggleStmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => $isActive ? 'Station activated' : 'Station deactivated'
                ]);
            } else {
                throw new Exception('Failed to toggle station');
            }
            break;
            
        case 'detect_mac':
            // Return current client's MAC address (if available via server variables)
            // Note: Browser cannot directly access MAC address for security reasons
            // This will return server-detected info only
            
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            
            echo json_encode([
                'success' => true,
                'ip_address' => $clientIp,
                'message' => 'MAC address must be manually entered. Check your network settings.',
                'help' => 'Windows: Run "ipconfig /all" in Command Prompt. Look for Physical Address.'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
