<?php
/**
 * Diagnostic Script for Queue Generation Errors
 * Run this file to identify issues with queue number generation
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Queue System Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .test { margin: 15px 0; padding: 15px; border-left: 4px solid #ccc; background: #f9f9f9; }
        .success { border-left-color: #4CAF50; background: #e8f5e9; }
        .error { border-left-color: #f44336; background: #ffebee; }
        .warning { border-left-color: #ff9800; background: #fff3e0; }
        .info { border-left-color: #2196F3; background: #e3f2fd; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .label { font-weight: bold; color: #666; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Queue System Diagnostics</h1>
        <p>Running diagnostic tests to identify queue generation issues...</p>";

// Test 1: Database Connection
echo "<div class='test'>";
echo "<div class='label'>Test 1: Database Connection</div>";
try {
    $conn = getDBConnection();
    if ($conn && !$conn->connect_error) {
        echo "<div class='success'>✓ Database connection successful</div>";
        echo "<div class='info'>Host: " . DB_HOST . "<br>Database: " . DB_NAME . "</div>";
    } else {
        echo "<div class='error'>✗ Database connection failed: " . ($conn ? $conn->connect_error : "Unknown error") . "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Database connection error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo "</div>";

if (isset($conn) && $conn && !$conn->connect_error) {
    
    // Test 2: Check Required Tables
    echo "<div class='test'>";
    echo "<div class='label'>Test 2: Required Database Tables</div>";
    $requiredTables = ['queue_counters', 'queues', 'queue_services', 'queue_logs'];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<div class='success'>✓ Table '$table' exists</div>";
            
            // Check table structure
            $desc = $conn->query("DESCRIBE $table");
            if ($desc) {
                echo "<div class='info'>Columns in '$table': ";
                $columns = [];
                while ($row = $desc->fetch_assoc()) {
                    $columns[] = $row['Field'] . " (" . $row['Type'] . ")";
                }
                echo implode(", ", $columns) . "</div>";
            }
        } else {
            echo "<div class='error'>✗ Table '$table' is MISSING</div>";
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        echo "<div class='warning'><strong>Action Required:</strong> Create the following missing tables: " . implode(", ", $missingTables) . "</div>";
    }
    echo "</div>";
    
    // Test 3: Check Table Engine (InnoDB for transactions)
    echo "<div class='test'>";
    echo "<div class='label'>Test 3: Table Storage Engine</div>";
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $engineResult = $conn->query("SHOW TABLE STATUS WHERE Name = '$table'");
            if ($engineResult && $row = $engineResult->fetch_assoc()) {
                $engine = $row['Engine'];
                if ($engine === 'InnoDB') {
                    echo "<div class='success'>✓ Table '$table' uses InnoDB (transactions supported)</div>";
                } else {
                    echo "<div class='warning'>⚠ Table '$table' uses $engine (transactions may not work, will proceed without transactions)</div>";
                }
            }
        }
    }
    echo "</div>";
    
    // Test 4: Test Queue Counter Operations
    echo "<div class='test'>";
    echo "<div class='label'>Test 4: Queue Counter Operations</div>";
    if (!in_array('queue_counters', $missingTables)) {
        try {
            // Try to read from queue_counters
            $testResult = $conn->query("SELECT * FROM queue_counters LIMIT 1");
            if ($testResult) {
                echo "<div class='success'>✓ Can read from queue_counters table</div>";
            }
            
            // Check if regular counter exists
            $regularCheck = $conn->prepare("SELECT current_number, last_reset_date FROM queue_counters WHERE queue_type = ?");
            if ($regularCheck) {
                $testType = 'regular';
                $regularCheck->bind_param("s", $testType);
                $regularCheck->execute();
                $result = $regularCheck->get_result();
                if ($result) {
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        echo "<div class='info'>Regular queue counter exists - Current: " . $row['current_number'] . ", Last Reset: " . $row['last_reset_date'] . "</div>";
                    } else {
                        echo "<div class='warning'>⚠ No 'regular' queue counter found (will be created automatically)</div>";
                    }
                }
                $regularCheck->close();
            }
        } catch (Exception $e) {
            echo "<div class='error'>✗ Error testing queue_counters: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='error'>✗ Cannot test - queue_counters table is missing</div>";
    }
    echo "</div>";
    
    // Test 5: Test Transaction Support
    echo "<div class='test'>";
    echo "<div class='label'>Test 5: Transaction Support</div>";
    try {
        $conn->begin_transaction();
        echo "<div class='success'>✓ Transactions are supported</div>";
        $conn->rollback();
    } catch (Exception $e) {
        echo "<div class='warning'>⚠ Transactions not supported: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div class='info'>The code will work without transactions, but with reduced data integrity protection</div>";
    }
    echo "</div>";
    
    // Test 6: Test generateQueueNumber Function
    echo "<div class='test'>";
    echo "<div class='label'>Test 6: Generate Queue Number Function</div>";
    if (!in_array('queue_counters', $missingTables)) {
        try {
            // Don't actually generate, just test the connection and table access
            echo "<div class='info'>Attempting to generate a test queue number (will rollback)...</div>";
            $testConn = getDBConnection();
            $queueNumber = generateQueueNumber($testConn, 'regular');
            echo "<div class='success'>✓ Successfully generated test queue number: $queueNumber</div>";
            
            // Clean up the test queue number from queues table if it exists
            $cleanup = $testConn->prepare("DELETE FROM queues WHERE queue_number = ?");
            if ($cleanup) {
                $cleanup->bind_param("s", $queueNumber);
                $cleanup->execute();
                $cleanup->close();
            }
            
            // Reset the counter
            $reset = $testConn->prepare("UPDATE queue_counters SET current_number = current_number - 1 WHERE queue_type = 'regular'");
            if ($reset) {
                $reset->execute();
                $reset->close();
            }
            
            $testConn->close();
        } catch (Exception $e) {
            echo "<div class='error'>✗ Error generating queue number: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<div class='info'><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre></div>";
        }
    } else {
        echo "<div class='error'>✗ Cannot test - queue_counters table is missing</div>";
    }
    echo "</div>";
    
    // Test 7: PHP Configuration
    echo "<div class='test'>";
    echo "<div class='label'>Test 7: PHP Configuration</div>";
    echo "<div class='info'>PHP Version: " . phpversion() . "</div>";
    echo "<div class='info'>MySQLi Extension: " . (extension_loaded('mysqli') ? '✓ Loaded' : '✗ Not Loaded') . "</div>";
    echo "<div class='info'>Error Reporting: " . (ini_get('error_reporting')) . "</div>";
    echo "<div class='info'>Display Errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "</div>";
    echo "</div>";
    
    $conn->close();
} else {
    echo "<div class='error'><strong>Cannot proceed with further tests - database connection failed</strong></div>";
}

echo "<div class='test info'>";
echo "<div class='label'>Next Steps:</div>";
echo "<ol>";
echo "<li>If tables are missing, you need to create them in your database</li>";
echo "<li>Check the error logs on your hosting server for detailed error messages</li>";
echo "<li>Ensure your database user has INSERT, UPDATE, SELECT permissions</li>";
echo "<li>If transactions don't work, the code will still function without them</li>";
echo "</ol>";
echo "</div>";

echo "</div></body></html>";
?>

