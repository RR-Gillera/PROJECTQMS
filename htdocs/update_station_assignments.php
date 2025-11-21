<?php
/**
 * Update station_assignments table with missing columns
 * Run this once to add the missing columns
 */

require_once __DIR__ . '/config.php';

try {
    $conn = getDBConnection();
    
    echo "Updating station_assignments table...\n<br>";
    
    // Add ip_address column if it doesn't exist
    $sql1 = "ALTER TABLE station_assignments 
             ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the station' AFTER counter_number";
    
    if ($conn->query($sql1)) {
        echo "✓ Added ip_address column\n<br>";
    } else {
        echo "Note: ip_address column may already exist or error: " . $conn->error . "\n<br>";
    }
    
    // Add is_active column if it doesn't exist
    $sql2 = "ALTER TABLE station_assignments 
             ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 COMMENT 'Whether this station is currently active' AFTER ip_address";
    
    if ($conn->query($sql2)) {
        echo "✓ Added is_active column\n<br>";
    } else {
        echo "Note: is_active column may already exist or error: " . $conn->error . "\n<br>";
    }
    
    // Add created_at column if it doesn't exist
    $sql3 = "ALTER TABLE station_assignments 
             ADD COLUMN IF NOT EXISTS created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER is_active";
    
    if ($conn->query($sql3)) {
        echo "✓ Added created_at column\n<br>";
    } else {
        echo "Note: created_at column may already exist or error: " . $conn->error . "\n<br>";
    }
    
    // Add updated_at column if it doesn't exist
    $sql4 = "ALTER TABLE station_assignments 
             ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at";
    
    if ($conn->query($sql4)) {
        echo "✓ Added updated_at column\n<br>";
    } else {
        echo "Note: updated_at column may already exist or error: " . $conn->error . "\n<br>";
    }
    
    echo "\n<br><strong>Update complete!</strong> You can now use the station registration feature.\n<br>";
    echo "<a href='register_station.html'>Go to Station Registration</a>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
