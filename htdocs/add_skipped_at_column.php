<?php
/**
 * Migration script to add skipped_at column to queues table
 * This enables precise tracking of when a queue was skipped for auto-cancellation after 1 hour
 */

require_once __DIR__ . '/config.php';

try {
    $conn = getDBConnection();
    
    // Check if column already exists
    $checkStmt = $conn->query("SHOW COLUMNS FROM queues LIKE 'skipped_at'");
    
    if ($checkStmt->num_rows > 0) {
        echo "Column 'skipped_at' already exists in queues table.\n";
        $checkStmt->close();
        $conn->close();
        exit;
    }
    $checkStmt->close();
    
    // Add the skipped_at column
    $sql = "ALTER TABLE queues ADD COLUMN skipped_at DATETIME NULL AFTER updated_at";
    
    if ($conn->query($sql)) {
        echo "Successfully added 'skipped_at' column to queues table.\n";
        echo "This column will track when a queue is skipped for auto-cancellation after 1 hour.\n";
    } else {
        throw new Exception("Error adding column: " . $conn->error);
    }
    
    // Update existing skipped queues to set skipped_at = updated_at
    $updateSql = "UPDATE queues SET skipped_at = updated_at WHERE status = 'skipped' AND skipped_at IS NULL";
    if ($conn->query($updateSql)) {
        $affected = $conn->affected_rows;
        echo "Updated $affected existing skipped queues with skipped_at timestamp.\n";
    }
    
    $conn->close();
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
