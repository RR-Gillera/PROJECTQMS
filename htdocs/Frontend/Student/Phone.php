<?php
session_start();
require_once 'db_config.php';

// Get queue number from URL or session
$queueNumber = $_GET['queue'] ?? $_SESSION['tracking_queue'] ?? '';

if (empty($queueNumber)) {
    header("Location: index.php");
    exit();
}

// Store in session for future refreshes
$_SESSION['tracking_queue'] = $queueNumber;

try {
    $conn = getDBConnection();
    
    // Get queue details
    $stmt = $conn->prepare("
        SELECT 
            q.*,
            (SELECT COUNT(*) FROM queues 
             WHERE status = 'waiting' 
             AND DATE(created_at) = CURDATE()
             AND ((queue_type = 'priority' AND q.queue_type = 'regular') 
                  OR (queue_type = q.queue_type AND created_at < q.created_at))
            ) as people_ahead,
            (SELECT COUNT(*) FROM queues 
             WHERE status = 'waiting' 
             AND DATE(created_at) = CURDATE()
             AND queue_type = q.queue_type
             AND created_at <= q.created_at
            ) as position_in_type
        FROM queues q
        WHERE q.queue_number = ? 
        AND DATE(q.created_at) = CURDATE()
    ");
    
    $stmt->bind_param("s", $queueNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $conn->close();
        die("Queue not found or expired.");
    }
    
    $queueData = $result->fetch_assoc();
    $stmt->close();
    
    // Calculate actual position (1-based)
    $position = ($queueData['people_ahead'] ?? 0) + 1;
    $peopleAhead = $queueData['people_ahead'] ?? 0;
    
    // Determine if priority
    $isPriority = ($queueData['queue_type'] === 'priority');
    
    // Get current serving queue
    $servingStmt = $conn->prepare("
        SELECT queue_number 
        FROM queues 
        WHERE status = 'serving' 
        AND DATE(created_at) = CURDATE()
        LIMIT 1
    ");
    $servingStmt->execute();
    $servingResult = $servingStmt->get_result();
    $currentlyServing = $servingResult->num_rows > 0 ? $servingResult->fetch_assoc()['queue_number'] : null;
    $servingStmt->close();
    
    $conn->close();
    
    // Set colors based on queue type
    if ($isPriority) {
        $bgGradient = "from-orange-100 to-red-200";
        $textColor = "text-red-700";
        $positionColor = "text-red-900";
        $borderColor = "border-red-700";
        $hoverBg = "hover:bg-red-700";
    } else {
        $bgGradient = "from-blue-100 to-blue-200";
        $textColor = "text-blue-700";
        $positionColor = "text-blue-900";
        $borderColor = "border-blue-700";
        $hoverBg = "hover:bg-blue-700";
    }
    
    // Check queue status for notifications
    $showNotification = false;
    $notificationMessage = '';
    
    if ($queueData['status'] === 'serving') {
        $showNotification = true;
        $notificationMessage = "It's your turn! Please proceed to counter " . ($queueData['window_number'] ?? 'N/A');
    } elseif ($peopleAhead <= 3 && $queueData['status'] === 'waiting') {
        $showNotification = true;
        $notificationMessage = "Your turn is approaching! Please prepare your documents.";
    }
    
} catch (Exception $e) {
    error_log("Error in Phone.php: " . $e->getMessage());
    die("An error occurred while fetching queue status.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>Queue Status - SeQueueR</title>
    <link rel="icon" type="image/png" href="/Frontend/favicon.php">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .queue-card {
            background: white;
            max-width: 400px;
            margin: 0 auto;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        .pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
    </style>
</head>
<body class="bg-white text-gray-700 flex flex-col min-h-screen">
    <?php include 'Header.php'; ?>
    
    <main class="flex-grow flex items-start justify-center pt-12 pb-20" style="background-image: linear-gradient(135deg, rgba(227, 242, 253, 0.3) 0%, rgba(225, 233, 240, 0.3) 20%, rgba(223, 227, 238, 0.3) 40%, rgba(212, 217, 232, 0.3) 60%, rgba(200, 208, 224, 0.3) 80%, rgba(188, 199, 216, 0.3) 100%), url('../Assests/Phone Background.png'); background-size: cover; background-position: center; background-repeat: no-repeat;">
        <div class="w-full max-w-md px-4">
            
            <!-- Status Alert -->
            <?php if ($queueData['status'] === 'completed'): ?>
            <div class="bg-green-100 border-l-4 border-green-500 p-4 rounded-lg flex items-start gap-3 mb-5">
                <i class="fas fa-check-circle text-green-600 text-xl mt-1"></i>
                <div class="text-green-800 text-sm font-medium leading-relaxed">
                    Your queue has been completed. Thank you!
                </div>
            </div>
            <?php elseif ($queueData['status'] === 'skipped'): ?>
            <div class="bg-red-100 border-l-4 border-red-500 p-4 rounded-lg flex items-start gap-3 mb-5">
                <i class="fas fa-exclamation-circle text-red-600 text-xl mt-1"></i>
                <div class="text-red-800 text-sm font-medium leading-relaxed">
                    Your queue was skipped. Please approach the counter.
                </div>
            </div>
            <?php elseif ($queueData['status'] === 'stalled'): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 rounded-lg flex items-start gap-3 mb-5">
                <i class="fas fa-pause-circle text-yellow-600 text-xl mt-1"></i>
                <div class="text-yellow-800 text-sm font-medium leading-relaxed">
                    Your queue is temporarily on hold. Please wait.
                </div>
            </div>
            <?php elseif ($showNotification): ?>
            <div class="bg-green-100 border-l-4 border-green-500 p-4 rounded-lg flex items-start gap-3 mb-5">
                <i class="fas fa-bell text-green-600 text-xl mt-1 pulse"></i>
                <div class="text-green-800 text-sm font-medium leading-relaxed">
                    <?php echo htmlspecialchars($notificationMessage); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Currently Serving -->
            <?php if ($currentlyServing && $queueData['status'] === 'waiting'): ?>
            <div class="bg-white border border-gray-200 rounded-lg p-4 mb-5 text-center">
                <div class="text-xs text-gray-600 mb-1">CURRENTLY SERVING</div>
                <div class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($currentlyServing); ?></div>
            </div>
            <?php endif; ?>

            <!-- Queue Card -->
            <div class="queue-card w-full">
                <div class="px-5 py-6">
                
                    <!-- Queue Number Section -->
                    <div class="mb-6">
                        <h3 class="text-gray-600 text-xs font-semibold text-center mb-3 tracking-wider flex items-center justify-center gap-2">
                            YOUR QUEUE NUMBER
                            <?php if ($isPriority): ?>
                            <span class="bg-red-600 text-white text-xs px-2 py-1 rounded-full font-bold">PRIORITY</span>
                            <?php endif; ?>
                        </h3>
                        <div class="bg-gradient-to-br <?php echo $bgGradient; ?> rounded-xl py-8 text-center">
                            <div class="text-6xl font-bold <?php echo $textColor; ?>"><?php echo htmlspecialchars($queueNumber); ?></div>
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div class="text-center mb-6">
                        <?php
                        $statusBadges = [
                            'waiting' => '<span class="inline-block bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-semibold">Waiting</span>',
                            'serving' => '<span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-semibold pulse">Now Serving</span>',
                            'completed' => '<span class="inline-block bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-xs font-semibold">Completed</span>',
                            'skipped' => '<span class="inline-block bg-red-100 text-red-800 px-3 py-1 rounded-full text-xs font-semibold">Skipped</span>',
                            'stalled' => '<span class="inline-block bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-xs font-semibold">On Hold</span>'
                        ];
                        echo $statusBadges[$queueData['status']] ?? '';
                        ?>
                    </div>

                    <!-- Position Section (only show if waiting) -->
                    <?php if ($queueData['status'] === 'waiting'): ?>
                    <div class="mb-6">
                        <h3 class="text-gray-600 text-xs font-semibold text-center mb-3 tracking-wider">YOUR POSITION</h3>
                        <div class="text-center">
                            <div class="flex items-center justify-center gap-2">
                                <span class="text-6xl font-bold <?php echo $positionColor; ?>"><?php echo $position; ?></span>
                                <span class="text-lg text-gray-600">in line</span>
                            </div>
                            <div class="flex items-center justify-center gap-2 mt-4 text-gray-600 text-sm">
                                <i class="fas fa-users"></i>
                                <span><?php echo $peopleAhead; ?> people ahead of you</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Queue Info -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Student:</span>
                                <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($queueData['student_name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Services:</span>
                                <span class="text-gray-900 font-medium"><?php echo $queueData['services_count']; ?> service(s)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Time:</span>
                                <span class="text-gray-900 font-medium">
                                    <?php 
                                    $time = new DateTime($queueData['created_at']);
                                    echo $time->format('g:i A');
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="space-y-3 mt-8">
                        <button onclick="refreshStatus()" class="w-full py-3 px-4 border-2 <?php echo $borderColor; ?> <?php echo $textColor; ?> <?php echo $hoverBg; ?> font-semibold rounded-lg flex items-center justify-center gap-2 hover:text-white transition">
                            <i class="fas fa-sync-alt"></i>
                            Refresh Status
                        </button>
                        <?php if ($queueData['status'] === 'waiting'): ?>
                        <button onclick="cancelQueue()" class="w-full py-3 px-4 border-2 border-red-600 text-red-600 font-semibold rounded-lg flex items-center justify-center gap-2 hover:bg-red-600 hover:text-white transition">
                            <i class="fas fa-times-circle"></i>
                            Cancel My Queue
                        </button>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
            
            <!-- Auto-refresh indicator -->
            <div class="text-center mt-4 text-xs text-gray-500">
                <i class="fas fa-sync-alt"></i> Auto-refreshing every 15 seconds
            </div>
        </div>
    </main>

    <script>
        const queueNumber = '<?php echo addslashes($queueNumber); ?>';
        const queueStatus = '<?php echo $queueData['status']; ?>';
        
        function refreshStatus() {
            location.reload();
        }

        function cancelQueue() {
            if (confirm('Are you sure you want to cancel your queue?')) {
                fetch('cancel_queue.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'queue_number=' + encodeURIComponent(queueNumber)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Queue cancelled successfully');
                        window.location.href = 'index.php';
                    } else {
                        alert('Failed to cancel queue: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while cancelling the queue');
                });
            }
        }

        // Auto-refresh every 15 seconds if status is waiting or serving
        if (queueStatus === 'waiting' || queueStatus === 'serving') {
            setInterval(function() {
                console.log('Auto-refreshing status...');
                location.reload();
            }, 15000);
        }

        // Check for notifications permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    </script>
    
    <?php include '../Footer.php'; ?>
</body>
</html>