<?php
/**
 * Queue System Reset Script
 * Clears all queue-related tables for testing/reset purposes
 * 
 * WARNING: This will delete ALL queue data!
 * Only run this when you want to completely reset the queue system.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to include config file with error handling
try {
    require_once 'config.php';
} catch (Exception $e) {
    die("❌ Error loading config.php: " . $e->getMessage());
}

// Set content type for web display
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Queue System Reset</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .button { 
            background-color: #dc3545; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px;
            margin: 10px 5px;
        }
        .button:hover { background-color: #c82333; }
        .confirm-section { 
            background-color: #fff3cd; 
            border: 1px solid #ffeaa7; 
            padding: 20px; 
            border-radius: 5px; 
            margin: 20px 0; 
        }
    </style>
</head>
<body>";

echo "<h1>🗑️ Queue System Reset Tool</h1>";

try {
    // Test database connection (config.php creates $conn variable directly)
    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed - \$conn variable not found");
    }
    
    // Test if connection is working
    if ($conn->connect_error) {
        throw new Exception("Database connection error: " . $conn->connect_error);
    }
    
    // Check if confirmation is provided
    $confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';
    
    if (!$confirmed) {
        // Show confirmation form
        echo "<div class='confirm-section'>";
        echo "<h2>⚠️ WARNING</h2>";
        echo "<p><strong>This action will permanently delete ALL data from the following tables:</strong></p>";
        echo "<ul>";
        echo "<li><code>queues</code> - All queue records</li>";
        echo "<li><code>queue_counters</code> - Queue numbering system</li>";
        echo "<li><code>queue_locks</code> - Queue locking system</li>";
        echo "<li><code>queue_logs</code> - All queue activity logs</li>";
        echo "<li><code>queue_services</code> - Queue service records</li>";
        echo "</ul>";
        
        // Show current data counts
        echo "<h3>Current Data Summary:</h3>";
        echo "<table>";
        echo "<tr><th>Table</th><th>Record Count</th><th>Description</th></tr>";
        
        $tables = [
            'queues' => 'All queue records (today and historical)',
            'queue_counters' => 'Queue numbering counters',
            'queue_locks' => 'Active queue locks',
            'queue_logs' => 'Queue activity history',
            'queue_services' => 'Queue service selections'
        ];
        
        foreach ($tables as $table => $description) {
            try {
                $countQuery = $conn->query("SELECT COUNT(*) as count FROM `$table`");
                $count = $countQuery->fetch_assoc()['count'];
                echo "<tr><td><code>$table</code></td><td>$count</td><td>$description</td></tr>";
            } catch (Exception $e) {
                echo "<tr><td><code>$table</code></td><td style='color: red;'>Error</td><td>$description</td></tr>";
            }
        }
        
        echo "</table>";
        
        echo "<p><strong style='color: red;'>This action cannot be undone!</strong></p>";
        
        echo "<div style='text-align: center; margin: 30px 0; padding: 20px; border: 2px solid red; background-color: #ffe6e6;'>";
        echo "<form method='POST' style='display: inline;'>";
        echo "<input type='hidden' name='confirm' value='yes'>";
        echo "<button type='submit' class='button' style='font-size: 18px; padding: 15px 30px; background-color: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;' onclick='return confirm(\"Are you absolutely sure? This will delete ALL queue data!\");'>🗑️ YES, DELETE ALL QUEUE DATA</button>";
        echo "</form>";
        echo "<br><br>";
        echo "<a href='?' style='color: blue; font-size: 16px; text-decoration: underline;'>← Cancel and go back</a>";
        echo "</div>";
        echo "</div>";
        
    } else {
        // Perform the reset
        echo "<h2>🚀 Performing Queue System Reset...</h2>";
        
        // Disable foreign key checks temporarily
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Tables to clear (order matters due to dependencies)
        $tablesToClear = [
            'queue_locks' => 'Queue locks (prevents race conditions)',
            'queue_services' => 'Queue service selections',
            'queue_logs' => 'Queue activity logs',
            'queues' => 'All queue records',
            'queue_counters' => 'Queue numbering system'
        ];
        
        echo "<table>";
        echo "<tr><th>Table</th><th>Records Before</th><th>Action</th><th>Status</th></tr>";
        
        $totalDeleted = 0;
        $errors = [];
        
        foreach ($tablesToClear as $table => $description) {
            echo "<tr>";
            echo "<td><code>$table</code><br><small>$description</small></td>";
            
            try {
                // Get count before deletion
                $countQuery = $conn->query("SELECT COUNT(*) as count FROM `$table`");
                $beforeCount = $countQuery->fetch_assoc()['count'];
                echo "<td>$beforeCount</td>";
                
                // Clear the table
                $deleteResult = $conn->query("DELETE FROM `$table`");
                
                if ($deleteResult) {
                    $deletedCount = $conn->affected_rows;
                    $totalDeleted += $deletedCount;
                    echo "<td>Deleted $deletedCount records</td>";
                    echo "<td class='success'>✅ Success</td>";
                } else {
                    echo "<td>-</td>";
                    echo "<td class='error'>❌ Failed: " . $conn->error . "</td>";
                    $errors[] = "Failed to clear $table: " . $conn->error;
                }
                
            } catch (Exception $e) {
                echo "<td>-</td>";
                echo "<td>-</td>";
                echo "<td class='error'>❌ Error: " . $e->getMessage() . "</td>";
                $errors[] = "Error clearing $table: " . $e->getMessage();
            }
            
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        // Reset auto-increment values for a clean start
        echo "<h3>🔄 Resetting Auto-Increment Values...</h3>";
        
        $autoIncrementTables = ['queues', 'queue_logs', 'queue_services'];
        
        foreach ($autoIncrementTables as $table) {
            try {
                $conn->query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
                echo "<p class='success'>✅ Reset auto-increment for <code>$table</code></p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Failed to reset auto-increment for <code>$table</code>: " . $e->getMessage() . "</p>";
            }
        }
        
        // Initialize queue counters for today
        echo "<h3>🎯 Initializing Queue Counters...</h3>";
        
        try {
            $today = date('Y-m-d');
            
            // Insert fresh counters for today
            $initQueries = [
                "INSERT INTO queue_counters (queue_type, current_number, last_reset_date) VALUES ('regular', 0, '$today')",
                "INSERT INTO queue_counters (queue_type, current_number, last_reset_date) VALUES ('priority', 0, '$today')"
            ];
            
            foreach ($initQueries as $query) {
                if ($conn->query($query)) {
                    echo "<p class='success'>✅ Initialized queue counter</p>";
                } else {
                    echo "<p class='error'>❌ Failed to initialize counter: " . $conn->error . "</p>";
                }
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error initializing counters: " . $e->getMessage() . "</p>";
        }
        
        // Summary
        echo "<div class='confirm-section'>";
        echo "<h2>📊 Reset Summary</h2>";
        
        if (empty($errors)) {
            echo "<p class='success'><strong>✅ Queue System Reset Completed Successfully!</strong></p>";
            echo "<p>Total records deleted: <strong>$totalDeleted</strong></p>";
            echo "<ul>";
            echo "<li>✅ All queue data cleared</li>";
            echo "<li>✅ Queue counters reset to 0</li>";
            echo "<li>✅ Auto-increment values reset</li>";
            echo "<li>✅ Fresh counters initialized for today</li>";
            echo "</ul>";
            echo "<p class='info'>The queue system is now ready for fresh data.</p>";
        } else {
            echo "<p class='error'><strong>⚠️ Reset completed with some errors:</strong></p>";
            echo "<ul>";
            foreach ($errors as $error) {
                echo "<li class='error'>$error</li>";
            }
            echo "</ul>";
        }
        
        echo "<p><a href='?' style='color: blue;'>← Back to Reset Tool</a></p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'><strong>❌ Database Connection Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

echo "</body></html>";
?>
