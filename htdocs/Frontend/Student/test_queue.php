<?php
session_start();
require_once 'db_config.php';

// Handle form submission for testing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_queue'])) {
    $testType = $_POST['queue_type'];
    $conn = getDBConnection();
    
    try {
        // Generate test queue number
        $queueNumber = generateQueueNumber($conn, $testType);
        
        // Insert test record
        $queueData = [
            'queue_number' => $queueNumber,
            'queue_type' => $testType,
            'student_name' => 'Test Student ' . date('H:i:s'),
            'student_id' => 'TEST-' . rand(1000, 9999),
            'year_level' => '3rd Year',
            'course_program' => 'Test Program',
            'services_count' => 1,
            'has_qr' => 0,
            'qr_code_url' => null
        ];
        
        $queueId = insertQueueRecord($conn, $queueData);
        logQueueAction($conn, $queueId, 'generated', 'Test queue generated');
        
        $message = "✅ Successfully generated: <strong>$queueNumber</strong> (Type: $testType)";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $messageType = 'error';
    }
    
    $conn->close();
}

// Get current counters
$conn = getDBConnection();
$result = $conn->query("SELECT * FROM queue_counters ORDER BY queue_type");
$counters = [];
while ($row = $result->fetch_assoc()) {
    $counters[$row['queue_type']] = $row;
}

// Get recent queues
$recentQueues = $conn->query("
    SELECT * FROM queues 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>Queue System Test Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-200 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
            <h1 class="text-3xl font-bold text-slate-900 mb-2">
                <i class="fas fa-vial text-blue-600"></i> Queue System Test Page
            </h1>
            <p class="text-slate-600">Test and verify the queue numbering system</p>
        </div>

        <?php if (isset($message)): ?>
        <div class="bg-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-50 border border-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-200 rounded-lg p-4 mb-6">
            <p class="text-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-800">
                <?php echo $message; ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Current Counters -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <?php foreach ($counters as $type => $counter): ?>
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-slate-900">
                        <?php echo ucfirst($type); ?> Queue
                    </h2>
                    <span class="text-3xl font-black text-<?php echo $type === 'priority' ? 'red' : 'blue'; ?>-600">
                        <?php echo $counter['current_number']; ?>
                    </span>
                </div>
                <div class="text-sm text-slate-600">
                    <p>Prefix: <strong><?php echo $type === 'priority' ? 'P' : 'R'; ?>-</strong></p>
                    <p>Next Number: <strong><?php echo ($type === 'priority' ? 'P' : 'R') . '-' . str_pad($counter['current_number'] + 1, 3, '0', STR_PAD_LEFT); ?></strong></p>
                    <p>Last Reset: <?php echo $counter['last_reset_date']; ?></p>
                </div>
                
                <form method="POST" class="mt-4">
                    <input type="hidden" name="queue_type" value="<?php echo $type; ?>">
                    <button type="submit" name="test_queue" 
                            class="w-full bg-<?php echo $type === 'priority' ? 'red' : 'blue'; ?>-600 text-white py-2 px-4 rounded hover:bg-<?php echo $type === 'priority' ? 'red' : 'blue'; ?>-700 transition">
                        <i class="fas fa-plus"></i> Generate Test <?php echo ucfirst($type); ?> Queue
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Queues -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-bold text-slate-900 mb-4">
                <i class="fas fa-history"></i> Recent Queue Numbers
            </h2>
            
            <?php if (empty($recentQueues)): ?>
            <p class="text-slate-500 text-center py-4">No queues generated yet</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="text-left py-3 px-4 font-semibold text-slate-700">Queue Number</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-700">Type</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-700">Student</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-700">QR</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-700">Status</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-700">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentQueues as $queue): ?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="py-3 px-4">
                                <span class="font-bold text-<?php echo $queue['queue_type'] === 'priority' ? 'red' : 'blue'; ?>-600">
                                    <?php echo htmlspecialchars($queue['queue_number']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-block bg-<?php echo $queue['queue_type'] === 'priority' ? 'red' : 'blue'; ?>-100 text-<?php echo $queue['queue_type'] === 'priority' ? 'red' : 'blue'; ?>-700 px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo ucfirst($queue['queue_type']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <?php echo htmlspecialchars($queue['student_name']); ?>
                                <br>
                                <span class="text-xs text-slate-500"><?php echo htmlspecialchars($queue['student_id']); ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($queue['has_qr']): ?>
                                <span class="text-green-600"><i class="fas fa-check-circle"></i> Yes</span>
                                <?php else: ?>
                                <span class="text-slate-400"><i class="fas fa-times-circle"></i> No</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-block bg-slate-100 text-slate-700 px-2 py-1 rounded text-xs">
                                    <?php echo ucfirst($queue['status']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-500">
                                <?php echo date('M d, Y H:i', strtotime($queue['created_at'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Debug Info -->
        <div class="bg-slate-800 text-white rounded-lg shadow-lg p-6 mt-6">
            <h2 class="text-xl font-bold mb-4">
                <i class="fas fa-bug"></i> Debug Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm font-mono">
                <div>
                    <p class="text-slate-400">Session Priority Group:</p>
                    <p class="text-green-400"><?php echo $_SESSION['priority_group'] ?? 'Not Set'; ?></p>
                </div>
                <div>
                    <p class="text-slate-400">Database Connection:</p>
                    <p class="text-green-400">
                        <?php 
                        try {
                            $testConn = getDBConnection();
                            echo "✓ Connected";
                            $testConn->close();
                        } catch (Exception $e) {
                            echo "✗ Failed";
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="index.php" class="text-blue-600 hover:text-blue-700">
                <i class="fas fa-arrow-left"></i> Back to Main Page
            </a>
        </div>
    </div>
</body>
</html>