<?php
/**
 * Script to create station assignment system for MAC address-based counter assignment
 * Run this file once to set up the station system
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Setting Up Station Assignment System</h2>";
echo "<pre>";

try {
    // Create station_assignments table
    echo "1. Creating station_assignments table...\n";
    $createStationAssignments = "
    CREATE TABLE IF NOT EXISTS station_assignments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        station_name VARCHAR(100) NOT NULL COMMENT 'Friendly name for the station (e.g., Counter 1 PC)',
        mac_address VARCHAR(17) NOT NULL UNIQUE COMMENT 'MAC address in format XX-XX-XX-XX-XX-XX',
        counter_number INT NOT NULL CHECK (counter_number BETWEEN 1 AND 4),
        ip_address VARCHAR(45) DEFAULT NULL COMMENT 'Optional IP address for additional verification',
        is_active BOOLEAN DEFAULT TRUE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT DEFAULT NULL COMMENT 'Admin ID who registered this station',
        INDEX idx_mac_address (mac_address),
        INDEX idx_counter_number (counter_number),
        INDEX idx_is_active (is_active),
        UNIQUE KEY unique_active_counter (counter_number, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($createStationAssignments)) {
        echo "   ✓ station_assignments table created successfully!\n";
    } else {
        if (strpos($conn->error, 'already exists') !== false) {
            echo "   ✓ station_assignments table already exists.\n";
        } else {
            throw new Exception("Error creating station_assignments table: " . $conn->error);
        }
    }
    
    echo "\n✅ Station assignment system setup completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Go to Settings page and click 'Station Management'\n";
    echo "2. Register each PC's MAC address with its counter number\n";
    echo "3. When users login from registered PCs, they'll auto-assign to the counter\n";
    echo "\nYour MAC address: BC-03-58-57-26-1E\n";
    echo "You can register this now by going to Admin Settings > Station Management\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

$conn->close();
?>
