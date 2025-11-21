<?php
// settings_handler.php - Handle settings CRUD operations
session_start();
require_once __DIR__ . '/../../Student/db_config.php';

header('Content-Type: application/json');

// Check if admin is logged in (add your authentication check here)
// if (!isset($_SESSION['admin_id'])) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit();
// }

$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = getDBConnection();
    
    switch ($method) {
        case 'GET':
            // Retrieve current settings
            $settings = getSystemSettings($conn);
            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
            break;
            
        case 'PUT':
        case 'POST':
            // Update settings
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                throw new Exception('Invalid input data');
            }
            
            // Validate input
            $validationErrors = validateSettings($input);
            if (!empty($validationErrors)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validationErrors
                ]);
                break;
            }
            
            // Save settings
            $success = saveSystemSettings($conn, $input);
            
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Settings saved successfully'
                ]);
            } else {
                throw new Exception('Failed to save settings');
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            break;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Settings error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Get system settings
function getSystemSettings($conn) {
    $stmt = $conn->prepare("
        SELECT setting_key, setting_value 
        FROM system_settings
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $settings = [
        'maxQueueCapacity' => 100,
        'skipTimeout' => 1,
        'skipTimeoutUnit' => 'hours',
        'sessionTimeout' => 30
    ];
    
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $stmt->close();
    
    // Convert numeric values
    $settings['maxQueueCapacity'] = (int)$settings['maxQueueCapacity'];
    $settings['skipTimeout'] = (int)$settings['skipTimeout'];
    $settings['sessionTimeout'] = (int)$settings['sessionTimeout'];
    
    return $settings;
}

// Save system settings
function saveSystemSettings($conn, $settings) {
    $conn->begin_transaction();
    
    try {
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_at = NOW()
            ");
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
            $stmt->close();
        }
        
        // Log the settings change
        logSettingsChange($conn, $settings);
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error saving settings: " . $e->getMessage());
        return false;
    }
}

// Validate settings
function validateSettings($settings) {
    $errors = [];
    
    if (isset($settings['maxQueueCapacity'])) {
        if ($settings['maxQueueCapacity'] < 1 || $settings['maxQueueCapacity'] > 500) {
            $errors['maxQueueCapacity'] = 'Must be between 1 and 500';
        }
    }
    
    if (isset($settings['skipTimeout'])) {
        if ($settings['skipTimeout'] < 1 || $settings['skipTimeout'] > 24) {
            $errors['skipTimeout'] = 'Must be between 1 and 24';
        }
    }
    
    if (isset($settings['skipTimeoutUnit'])) {
        if (!in_array($settings['skipTimeoutUnit'], ['minutes', 'hours'])) {
            $errors['skipTimeoutUnit'] = 'Must be either minutes or hours';
        }
    }
    
    if (isset($settings['sessionTimeout'])) {
        if ($settings['sessionTimeout'] < 5 || $settings['sessionTimeout'] > 120) {
            $errors['sessionTimeout'] = 'Must be between 5 and 120 minutes';
        }
    }
    
    return $errors;
}

// Log settings changes
function logSettingsChange($conn, $settings) {
    $changes = json_encode($settings);
    $adminId = $_SESSION['admin_id'] ?? 0;
    
    $stmt = $conn->prepare("
        INSERT INTO settings_log (admin_id, changes, created_at)
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param("is", $adminId, $changes);
    $stmt->execute();
    $stmt->close();
}
?>