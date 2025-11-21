<?php
/**
 * Script to create counter assignment system
 * Run this file once in your browser or via command line to set up the counter system
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Setting Up Counter Assignment System</h2>";
echo "<pre>";

try {
    // 1. Create counter_assignments table
    echo "1. Creating counter_assignments table...\n";
    $createCounterAssignments = "
    CREATE TABLE IF NOT EXISTS counter_assignments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id VARCHAR(50) NOT NULL,
        counter_number INT NOT NULL CHECK (counter_number BETWEEN 1 AND 4),
        assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        released_at DATETIME NULL DEFAULT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        INDEX idx_student_id (student_id),
        INDEX idx_counter_number (counter_number),
        INDEX idx_is_active (is_active),
        UNIQUE KEY unique_active_counter (counter_number, is_active),
        FOREIGN KEY (student_id) REFERENCES Accounts(StudentID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    
    -- Note: MySQL doesn't support filtered unique indexes easily, so we'll handle uniqueness in application logic
    -- The unique constraint will prevent multiple records with same counter_number AND is_active value
    -- We ensure only one active assignment per counter by checking in the application
    ";
    
    if ($conn->query($createCounterAssignments)) {
        echo "   ✓ counter_assignments table created successfully!\n";
    } else {
        // Check if table already exists
        if (strpos($conn->error, 'already exists') !== false) {
            echo "   ✓ counter_assignments table already exists.\n";
        } else {
            throw new Exception("Error creating counter_assignments table: " . $conn->error);
        }
    }
    
    // 2. Add counter_id column to queues table if it doesn't exist
    echo "\n2. Adding counter_id column to queues table...\n";
    $checkCounterId = $conn->query("SHOW COLUMNS FROM queues LIKE 'counter_id'");
    if ($checkCounterId && $checkCounterId->num_rows > 0) {
        echo "   ✓ counter_id column already exists in queues table.\n";
    } else {
        $addCounterId = "ALTER TABLE queues ADD COLUMN counter_id INT NULL DEFAULT NULL AFTER window_number";
        if ($conn->query($addCounterId)) {
            echo "   ✓ counter_id column added successfully!\n";
        } else {
            throw new Exception("Error adding counter_id column: " . $conn->error);
        }
    }
    
    // 3. Add worker_id column to queues table if it doesn't exist (to track which worker handled the queue)
    echo "\n3. Adding worker_id column to queues table...\n";
    $checkWorkerId = $conn->query("SHOW COLUMNS FROM queues LIKE 'worker_id'");
    if ($checkWorkerId && $checkWorkerId->num_rows > 0) {
        echo "   ✓ worker_id column already exists in queues table.\n";
    } else {
        $addWorkerId = "ALTER TABLE queues ADD COLUMN worker_id VARCHAR(50) NULL DEFAULT NULL AFTER counter_id";
        if ($conn->query($addWorkerId)) {
            echo "   ✓ worker_id column added successfully!\n";
        } else {
            throw new Exception("Error adding worker_id column: " . $conn->error);
        }
    }
    
    // 4. Add served_by column to queues table if it doesn't exist (alternative to worker_id)
    echo "\n4. Verifying served_by column...\n";
    $checkServedBy = $conn->query("SHOW COLUMNS FROM queues LIKE 'served_by'");
    if ($checkServedBy && $checkServedBy->num_rows > 0) {
        echo "   ✓ served_by column already exists.\n";
    } else {
        echo "   ℹ served_by column not found (optional, may not be needed).\n";
    }
    
    echo "\n✅ Counter assignment system setup completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Workers will be automatically assigned to counters 1-4 on login\n";
    echo "2. Each counter can only have 1 active worker at a time\n";
    echo "3. Counter assignment is released on logout\n";
    echo "4. Transaction history will be filtered by worker's counter\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "\nYou may need to run these SQL commands manually:\n";
    echo "CREATE TABLE IF NOT EXISTS counter_assignments (...);\n";
    echo "ALTER TABLE queues ADD COLUMN counter_id INT NULL DEFAULT NULL;\n";
    echo "ALTER TABLE queues ADD COLUMN worker_id VARCHAR(50) NULL DEFAULT NULL;\n";
}

echo "</pre>";
echo "<p><a href='Frontend/Personnel/Signin.php'>Go to Login Page</a></p>";
?>

