<?php
// Fix existing queue assignments to respect counter types
// Run this after updating your database structure

require_once 'config.php';

try {
    // Get all active queues that violate the counter-type rules
    $violationsQuery = "
        SELECT 
            id,
            queue_number,
            queue_type,
            window_number,
            status,
            student_name
        FROM queues 
        WHERE (
            (window_number IN (1, 2, 3) AND queue_type = 'priority') OR
            (window_number = 4 AND queue_type = 'regular')
        ) AND status IN ('waiting', 'serving')
        ORDER BY created_at ASC
    ";
    
    $stmt = $conn->prepare($violationsQuery);
    $stmt->execute();
    $violations = $stmt->get_result();
    
    echo "<h2>Queue Assignment Violations Found:</h2>\n";
    
    if ($violations->num_rows == 0) {
        echo "<p>No violations found! All queues are properly assigned.</p>\n";
    } else {
        echo "<table border='1'>\n";
        echo "<tr><th>Queue Number</th><th>Type</th><th>Current Counter</th><th>Student</th><th>Status</th><th>Action</th></tr>\n";
        
        $fixedCount = 0;
        
        while ($violation = $violations->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($violation['queue_number']) . "</td>";
            echo "<td>" . htmlspecialchars($violation['queue_type']) . "</td>";
            echo "<td>" . htmlspecialchars($violation['window_number']) . "</td>";
            echo "<td>" . htmlspecialchars($violation['student_name']) . "</td>";
            echo "<td>" . htmlspecialchars($violation['status']) . "</td>";
            
            // Reset the queue assignment
            $resetStmt = $conn->prepare("
                UPDATE queues 
                SET window_number = NULL, 
                    counter_id = NULL, 
                    worker_id = NULL, 
                    status = 'waiting'
                WHERE id = ?
            ");
            $resetStmt->bind_param("i", $violation['id']);
            
            if ($resetStmt->execute()) {
                echo "<td style='color: green;'>✓ Reset to waiting</td>";
                $fixedCount++;
            } else {
                echo "<td style='color: red;'>✗ Failed to reset</td>";
            }
            $resetStmt->close();
            
            echo "</tr>\n";
        }
        
        echo "</table>\n";
        echo "<p><strong>Fixed $fixedCount violations.</strong></p>\n";
    }
    
    $stmt->close();
    
    // Show current counter configuration
    echo "<h2>Current Counter Configuration:</h2>\n";
    $configQuery = "SELECT window_number, counter_type, is_active FROM counter_status ORDER BY window_number";
    $configStmt = $conn->prepare($configQuery);
    $configStmt->execute();
    $config = $configStmt->get_result();
    
    echo "<table border='1'>\n";
    echo "<tr><th>Counter</th><th>Type</th><th>Status</th><th>Accepts</th></tr>\n";
    
    while ($counter = $config->fetch_assoc()) {
        echo "<tr>";
        echo "<td>Counter " . $counter['window_number'] . "</td>";
        echo "<td>" . ucfirst($counter['counter_type']) . "</td>";
        echo "<td>" . ($counter['is_active'] ? 'Active' : 'Inactive') . "</td>";
        
        if ($counter['counter_type'] == 'regular') {
            echo "<td>Regular queues only (R-xxx)</td>";
        } else {
            echo "<td>Priority queues only (P-xxx)</td>";
        }
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    $configStmt->close();
    
    echo "<h2>Summary:</h2>\n";
    echo "<ul>\n";
    echo "<li>Counters 1, 2, 3: Handle <strong>regular</strong> queues only</li>\n";
    echo "<li>Counter 4: Handles <strong>priority</strong> queues only</li>\n";
    echo "<li>All violations have been reset to 'waiting' status</li>\n";
    echo "<li>The updated application code will now respect these rules</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
