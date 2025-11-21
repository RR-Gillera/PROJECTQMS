<?php 
// TV Board - Now Serving and Next in Queue
// This file serves the initial HTML and data via AJAX API endpoint
// API endpoint: /Frontend/Tv.php?action=api
require_once __DIR__ . '/Student/db_config.php';

// API endpoint for AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'api') {
    header('Content-Type: application/json; charset=utf-8');
    
    $servingQueues = [];
    $nextQueues = [];
    
    try {
        $conn = getDBConnection();
        
        // Get currently serving queues for each counter
        $servingStmt = $conn->prepare("
            SELECT 
                q.id,
                q.queue_number,
                q.queue_type,
                q.counter_id,
                q.status,
                q.served_at
            FROM queues q
            WHERE q.status = 'serving'
            AND DATE(q.created_at) = CURDATE()
            AND q.counter_id IN (1, 2, 3, 4)
            ORDER BY q.counter_id ASC, q.served_at DESC
        ");
        
        if ($servingStmt) {
            $servingStmt->execute();
            $servingResult = $servingStmt->get_result();
            
            $counterServing = [];
            while ($row = $servingResult->fetch_assoc()) {
                // Counter 4 should only show priority queues
                if ($row['counter_id'] == 4 && strpos($row['queue_number'], 'P-') !== 0) {
                    continue;
                }
                
                if (!isset($counterServing[$row['counter_id']])) {
                    $counterServing[$row['counter_id']] = $row['queue_number'];
                }
            }
            
            // Initialize all counters (1-4)
            for ($i = 1; $i <= 4; $i++) {
                $servingQueues[$i] = $counterServing[$i] ?? '--';
            }
            
            $servingStmt->close();
        }
        
        // Get next queues in queue (waiting status)
        $nextStmt = $conn->prepare("
            SELECT 
                q.id,
                q.queue_number,
                q.queue_type,
                q.created_at
            FROM queues q
            WHERE q.status = 'waiting'
            AND DATE(q.created_at) = CURDATE()
            ORDER BY 
                CASE WHEN q.queue_type = 'priority' THEN 0 ELSE 1 END ASC,
                q.created_at ASC
            LIMIT 6
        ");
        
        if ($nextStmt) {
            $nextStmt->execute();
            $nextResult = $nextStmt->get_result();
            
            while ($row = $nextResult->fetch_assoc()) {
                $nextQueues[] = $row['queue_number'];
            }
            
            $nextStmt->close();
        }
        
        $conn->close();
    } catch (Exception $e) {
        error_log("TV Board API Error: " . $e->getMessage());
    }
    
    // Ensure we have exactly 6 slots for next in queue
    while (count($nextQueues) < 6) {
        $nextQueues[] = '--';
    }
    $nextQueues = array_slice($nextQueues, 0, 6);
    
    echo json_encode([
        'servingQueues' => $servingQueues,
        'nextQueues' => $nextQueues,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Initial page load - serve HTML with empty placeholders
$servingQueues = ['--', '--', '--', '--'];
$nextQueues = ['--', '--', '--', '--', '--', '--'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SeQueueR TV Board</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .tv-counter-number {
            font-size: 2.75rem;
        }
        @media (min-width: 768px) {
            .tv-counter-number {
                font-size: 3.5rem;
            }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col" style="background-image: url('Assests/QueueReqPic.png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">
    <?php include __DIR__ . '/TvHeader.php'; ?>

    <main class="flex-grow mt-8">
        <div class="px-6 md:px-10 mx-16 md:mx-32 lg:mx-48 py-6 md:py-10">
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Left: Now Serving -->
                <div class="lg:flex-[7]">
                    <div class="bg-blue-900 text-white rounded-2xl shadow-lg px-6 py-4 mb-6">
                        <h2 class="text-center text-3xl md:text-4xl font-extrabold tracking-wider">NOW SERVING</h2>
                    </div>

                    <!-- Counter 1 -->
                    <div class="flex items-stretch gap-0 mb-9 rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-blue-900 text-white px-20 py-10 flex items-center justify-center">
                            <span class="text-xl md:text-2xl font-extrabold tracking-wide">COUNTER 1</span>
                        </div>
                        <div class="flex-1 bg-white border-2 border-blue-900 rounded-r-2xl px-8 py-10 flex items-center justify-center">
                            <span id="counter-1" class="text-gray-300 font-bold text-3xl md:text-4xl">--</span>
                        </div>
                    </div>

                    <!-- Counter 2 -->
                    <div class="flex items-stretch gap-0 mb-9 rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-blue-900 text-white px-20 py-10 flex items-center justify-center">
                            <span class="text-xl md:text-2xl font-extrabold tracking-wide">COUNTER 2</span>
                        </div>
                        <div class="flex-1 bg-white border-2 border-blue-900 rounded-r-2xl px-8 py-10 flex items-center justify-center">
                            <span id="counter-2" class="text-gray-300 font-bold text-3xl md:text-4xl">--</span>
                        </div>
                    </div>

                    <!-- Counter 3 -->
                    <div class="flex items-stretch gap-0 mb-9 rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-blue-900 text-white px-20 py-10 flex items-center justify-center">
                            <span class="text-xl md:text-2xl font-extrabold tracking-wide">COUNTER 3</span>
                        </div>
                        <div class="flex-1 bg-white border-2 border-blue-900 rounded-r-2xl px-8 py-10 flex items-center justify-center">
                            <span id="counter-3" class="text-gray-300 font-bold text-3xl md:text-4xl">--</span>
                        </div>
                    </div>

                    <!-- Counter 4 -->
                    <div class="flex items-stretch gap-0 mb-9 rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-blue-900 text-white px-20 py-10 flex items-center justify-center">
                            <span class="text-xl md:text-2xl font-extrabold tracking-wide">COUNTER 4</span>
                        </div>
                        <div class="flex-1 bg-white border-2 border-blue-900 rounded-r-2xl px-8 py-10 flex items-center justify-center">
                            <span id="counter-4" class="text-gray-300 font-bold text-3xl md:text-4xl">--</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Next in Queue -->
                <aside class="lg:flex-[2.5] w-full self-stretch">
                    <div class="rounded-2xl shadow-lg overflow-hidden w-full max-w-[280px] bg-white ml-auto h-[calc(100%-25px)] flex flex-col">
                        <div class="bg-blue-900 text-white px-5 py-4">
                            <h3 class="text-2xl md:text-3xl font-extrabold tracking-wider text-center">NEXT IN QUEUE</h3>
                        </div>
                        <div class="p-6 space-y-5 flex-1">
                            <!-- Display next 6 queues -->
                            <?php for ($i = 0; $i < 6; $i++): ?>
                            <div class="bg-gray-100 rounded-xl px-3 h-[60px] flex items-center justify-center w-[180px] mx-auto">
                                <span id="next-queue-<?php echo $i; ?>" class="text-gray-400 text-lg">--</span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/TvFooter.php'; ?>

    <script>
        // Auto-update every 2 seconds without page refresh
        const updateInterval = 2000; // 2 seconds
        
        function updateQueue() {
            fetch('<?php echo $_SERVER["PHP_SELF"]; ?>?action=api', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.servingQueues && data.nextQueues) {
                    // Update serving queues for counters 1-4
                    for (let i = 1; i <= 4; i++) {
                        const queueNumber = data.servingQueues[i] || '--';
                        const element = document.getElementById(`counter-${i}`);
                        if (element) {
                            let displayText = queueNumber;
                            // Add star for priority queues at counter 4
                            if (i === 4 && queueNumber !== '--' && queueNumber.startsWith('P-')) {
                                displayText = '⭐ ' + queueNumber;
                            }
                            
                            // Only update if different (prevent unnecessary DOM updates)
                            if (element.textContent !== displayText) {
                                element.textContent = displayText;
                                element.className = (queueNumber === '--') ? 'text-gray-300 font-bold text-3xl md:text-4xl' : 'text-blue-900 font-bold text-3xl md:text-4xl';
                            }
                        }
                    }
                    
                    // Update next in queue
                    for (let i = 0; i < 6; i++) {
                        const queueNumber = data.nextQueues[i] || '--';
                        const element = document.getElementById(`next-queue-${i}`);
                        if (element) {
                            // Only update if different
                            if (element.textContent !== queueNumber) {
                                element.textContent = queueNumber;
                                
                                // Update styling based on queue type
                                const container = element.parentElement;
                                container.className = queueNumber === '--' 
                                    ? 'bg-gray-100 rounded-xl px-3 h-[60px] flex items-center justify-center w-[180px] mx-auto'
                                    : (queueNumber.startsWith('P-') 
                                        ? 'bg-red-100 rounded-xl px-3 h-[60px] flex items-center justify-center w-[180px] mx-auto'
                                        : 'bg-blue-100 rounded-xl px-3 h-[60px] flex items-center justify-center w-[180px] mx-auto');
                                
                                element.className = queueNumber === '--'
                                    ? 'text-gray-400 text-lg'
                                    : (queueNumber.startsWith('P-')
                                        ? 'text-red-700 font-bold text-lg'
                                        : 'text-blue-600 font-bold text-lg');
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching queue data:', error);
            });
        }
        
        // Update immediately on page load
        updateQueue();
        
        // Then update every 2 seconds
        setInterval(updateQueue, updateInterval);
    </script>
</body>
</html>
