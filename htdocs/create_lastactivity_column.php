<?php
/**
 * Script to add LastActivity column to Accounts table
 * Run this file once in your browser or via command line to create the column
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Adding LastActivity Column to Accounts Table</h2>";
echo "<pre>";

// Check if column already exists
$checkColumn = $conn->query("SHOW COLUMNS FROM Accounts LIKE 'LastActivity'");
if ($checkColumn && $checkColumn->num_rows > 0) {
    echo "✓ LastActivity column already exists in Accounts table.\n";
    echo "No action needed.\n";
} else {
    echo "LastActivity column does not exist. Creating it now...\n\n";
    
    // Create the column
    $sql = "ALTER TABLE Accounts ADD COLUMN LastActivity DATETIME NULL DEFAULT NULL";
    
    if ($conn->query($sql)) {
        echo "✓ Successfully added LastActivity column to Accounts table!\n\n";
        
        // Verify it was created
        $verify = $conn->query("SHOW COLUMNS FROM Accounts LIKE 'LastActivity'");
        if ($verify && $verify->num_rows > 0) {
            echo "✓ Column verified successfully!\n";
            echo "\nThe LastActivity column is now ready to track when users are online.\n";
            echo "Users' activity will be updated automatically when they:\n";
            echo "  - Log in\n";
            echo "  - Use the keepalive system (every 2 minutes)\n";
        } else {
            echo "⚠ Warning: Column creation reported success but verification failed.\n";
        }
    } else {
        echo "✗ Error creating column: " . $conn->error . "\n";
        echo "\nYou may need to run this SQL manually:\n";
        echo "ALTER TABLE Accounts ADD COLUMN LastActivity DATETIME NULL DEFAULT NULL;\n";
    }
}

echo "</pre>";
echo "<p><a href='Frontend/Personnel/Admin/User.php'>Go to Users Page</a></p>";
?>

