<?php
// Queue Management Dashboard for SeQueueR - Admin
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// DEBUG: show assigned counter number (remove after testing)
echo 'DEBUG Counter: ' . ($_SESSION['user']['counterNumber'] ?? 'none') . '<br>';

require_once __DIR__ . '/../../Student/db_config.php';
require_once __DIR__ . '/../admin_functions.php';

include_once __DIR__ . '/auto_cancel_trigger.php';

// Get database connection
$conn = getDBConnection();

// Get current serving queue
$currentlyServing = getCurrentlyServing($conn);
$currentQueue = !empty($currentlyServing) ? $currentlyServing[0] : null;

// Get queue details if there's a current queue
$currentQueueDetails = null;
$currentServices = [];
if ($currentQueue) {
    $currentQueueDetails = getQueueDetails($conn, $currentQueue['id']);
    if ($currentQueueDetails && $currentQueueDetails['services']) {
        $currentServices = explode(', ', $currentQueueDetails['services']);
    }
}

// Get waiting queues (active)
$waitingQueues = getWaitingQueuesList($conn, 50);

// Get statistics
$stats = getQueueStatistics($conn);

// Get stalled and skipped queues
$stalledQuery = "SELECT * FROM queues WHERE status = 'stalled' AND DATE(created_at) = CURDATE() ORDER BY created_at ASC";
$stalledResult = $conn->query($stalledQuery);
$stalledQueues = $stalledResult->fetch_all(MYSQLI_ASSOC);

$skippedQuery = "SELECT * FROM queues WHERE status = 'skipped' AND DATE(created_at) = CURDATE() ORDER BY created_at ASC";
$skippedResult = $conn->query($skippedQuery);
$skippedQueues = $skippedResult->fetch_all(MYSQLI_ASSOC);

// Get completed count
$completedCount = $stats['completed'];

// Calculate average service time
$avgTimeQuery = "
    SELECT AVG(TIMESTAMPDIFF(MINUTE, served_at, completed_at)) as avg_time
    FROM queues
    WHERE status = 'completed' 
    AND DATE(created_at) = CURDATE()
    AND served_at IS NOT NULL 
    AND completed_at IS NOT NULL
";
$avgTimeResult = $conn->query($avgTimeQuery);
$avgTimeRow = $avgTimeResult->fetch_assoc();
$avgServiceTime = round($avgTimeRow['avg_time'] ?? 0);

// Close connection
$conn->close();

// Check if there's an error from queue_actions.php
$hasError = isset($_GET['error']);

// Initialize pause state from session
$isPaused = isset($_SESSION['queue_paused']) ? $_SESSION['queue_paused'] : false;

// Calculate total wait time for current queue
$totalWaitTime = '--';
if ($currentQueueDetails) {
    // Convert database time to Manila timezone
    $createdTime = new DateTime($currentQueueDetails['created_at'], new DateTimeZone('UTC'));
    $createdTime->setTimezone(new DateTimeZone('Asia/Manila'));
    
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $diff = $now->diff($createdTime);
    
    if ($diff->h > 0) {
        $totalWaitTime = $diff->h . 'h ' . $diff->i . 'm ' . $diff->s . 's';
    } elseif ($diff->i > 0) {
        $totalWaitTime = $diff->i . 'm ' . $diff->s . 's';
    } else {
        $totalWaitTime = $diff->s . 's';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Management - SeQueueR</title>
    <link rel="icon" type="image/png" href="/Frontend/favicon.php">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
        .disabled-btn {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'Header.php'; ?>
    
    <main class="bg-gray-100 min-h-screen">
        <div class="py-8 px-6 md:px-10 mx-4 md:mx-8 lg:mx-12">
            
            <?php if ($hasError): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                <div class="flex">
                    <div class="py-1"><i class="fas fa-exclamation-circle mr-2"></i></div>
                    <div>
                        <p class="font-bold">Error</p>
                        <p class="text-sm">An error occurred while processing the queue action. Please try again.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($isPaused): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded" role="alert">
                <div class="flex">
                    <div class="py-1"><i class="fas fa-pause-circle mr-2"></i></div>
                    <div>
                        <p class="font-bold">Queue System Paused</p>
                        <p class="text-sm">Queue operations are currently paused. Click "RESUME QUEUE" to continue.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-10 gap-8">
                <!-- Left Panel -->
                <div class="lg:col-span-7 space-y-6">
                    <!-- Currently Serving Card -->
                    <div class="bg-white border-2 <?php echo $currentQueue ? 'border-yellow-400' : 'border-gray-300'; ?> rounded-lg p-8 text-center shadow-sm">
                        <div id="currentQueueNumber" class="text-6xl font-bold <?php echo $currentQueue ? 'text-yellow-400' : 'text-gray-300'; ?> mb-3">
                            <?php echo $currentQueue ? htmlspecialchars($currentQueue['queue_number']) : '--'; ?>
                        </div>
                        <div class="flex items-center justify-center space-x-2">
                            <div class="w-3 h-3 <?php echo $currentQueue && !$isPaused ? 'bg-green-500 pulse-animation' : 'bg-gray-300'; ?> rounded-full"></div>
                            <span class="<?php echo $currentQueue && !$isPaused ? 'text-green-600' : 'text-gray-500'; ?> font-medium">
                                <?php echo $currentQueue ? ($isPaused ? 'Queue Paused' : 'Currently Serving') : 'No Queue Serving'; ?>
                            </span>
                        </div>
                        <?php if ($currentQueue): ?>
                        <div class="mt-2 text-sm text-gray-600">
                            Counter <?php echo $currentQueue['window_number'] ?? '1'; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Student Information & Queue Details -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Student Information -->
                            <div>
                                <h3 class="text-lg font-bold text-blue-800 mb-6 pb-2 border-b border-gray-200">Student Information</h3>
                                <div class="space-y-4">
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Full Name</span>
                                        <p id="studentName" class="font-bold text-gray-800 text-base">
                                            <?php echo $currentQueueDetails ? htmlspecialchars($currentQueueDetails['student_name']) : '--'; ?>
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Student ID</span>
                                        <p id="studentId" class="font-bold text-gray-800 text-base">
                                            <?php echo $currentQueueDetails ? htmlspecialchars($currentQueueDetails['student_id']) : '--'; ?>
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Course</span>
                                        <p id="studentCourse" class="font-bold text-gray-800 text-base">
                                            <?php echo $currentQueueDetails ? htmlspecialchars($currentQueueDetails['course_program']) : '--'; ?>
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Year Level</span>
                                        <p id="studentYear" class="font-bold text-gray-800 text-base">
                                            <?php echo $currentQueueDetails ? htmlspecialchars($currentQueueDetails['year_level']) : '--'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Queue Details -->
                            <div>
                                <h3 class="text-lg font-bold text-blue-800 mb-6 pb-2 border-b border-gray-200">Queue Details</h3>
                                <div class="space-y-4">
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-2">Priority Type</span>
                                        <div id="priorityType">
                                            <?php if ($currentQueueDetails && $currentQueueDetails['queue_type'] === 'priority'): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-yellow-200 text-gray-800">
                                                <i class="fas fa-star mr-2 text-black"></i>
                                                Priority
                                            </span>
                                            <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-gray-200 text-gray-800">
                                                Regular
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Time Requested</span>
                                        <p id="timeRequested" class="font-bold text-gray-800 text-base">
                                            <?php 
                                            if ($currentQueueDetails) {
                                                try {
                                                    $time = new DateTime($currentQueueDetails['created_at'], new DateTimeZone('UTC'));
                                                    $time->setTimezone(new DateTimeZone('Asia/Manila'));
                                                    echo $time->format('g:i A');
                                                } catch (Exception $e) {
                                                    $time = new DateTime($currentQueueDetails['created_at']);
                                                    echo $time->format('g:i A');
                                                }
                                            } else {
                                                echo '--';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Total Wait Time</span>
                                        <p class="font-bold text-gray-800 text-base" id="totalWaitTime">
                                            <?php echo $totalWaitTime; ?>
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Current Time</span>
                                        <p class="font-bold text-gray-800 text-base" id="currentTime">
                                            <?php 
                                            $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
                                            echo $now->format('g:i A');
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Requested Services -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                        <h3 class="text-lg font-bold text-blue-800 mb-6">Requested Services</h3>
                        
                        <?php if (empty($currentServices)): ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-clipboard-list text-4xl mb-4"></i>
                            <p class="text-lg font-medium">No services requested</p>
                            <p class="text-sm">Services will appear here when a student requests them</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($currentServices as $index => $service): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-file-alt text-blue-600"></i>
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($service); ?></span>
                                    </div>
                                    <span class="text-sm text-gray-500">#<?php echo $index + 1; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Buttons -->
                    <div class="grid grid-cols-2 gap-4">
                        <button onclick="callNextQueue()" 
                                <?php echo $isPaused ? 'disabled' : ''; ?>
                                class="<?php echo $isPaused ? 'bg-gray-300 cursor-not-allowed disabled-btn' : 'bg-green-600 hover:bg-green-700'; ?> text-white font-semibold py-3 px-6 rounded-lg flex items-center justify-center space-x-2 shadow-md transition">
                            <i class="fas fa-forward"></i>
                            <span>COMPLETE & NEXT</span>
                        </button>
                        <button onclick="stallQueue()" 
                                <?php echo !$currentQueue || $isPaused ? 'disabled' : ''; ?>
                                class="<?php echo (!$currentQueue || $isPaused) ? 'bg-gray-300 cursor-not-allowed disabled-btn' : 'bg-yellow-500 hover:bg-yellow-600'; ?> text-black font-semibold py-3 px-6 rounded-lg flex items-center justify-center space-x-2 shadow-md transition">
                            <i class="fas fa-pause"></i>
                            <span>MARK AS STALLED</span>
                        </button>
                        <button onclick="skipQueue()" 
                                <?php echo !$currentQueue || $isPaused ? 'disabled' : ''; ?>
                                class="<?php echo (!$currentQueue || $isPaused) ? 'bg-gray-300 cursor-not-allowed disabled-btn' : 'bg-blue-900 hover:bg-blue-800'; ?> text-white font-semibold py-3 px-6 rounded-lg flex items-center justify-center space-x-2 shadow-md transition">
                            <i class="fas fa-step-forward"></i>
                            <span>SKIP QUEUE</span>
                        </button>
                        <button onclick="togglePauseResume()" 
                                class="<?php echo $isPaused ? 'bg-gray-600 hover:bg-gray-700 text-white' : 'bg-white border-2 border-gray-500 text-gray-600 hover:bg-gray-50'; ?> font-semibold py-3 px-6 rounded-lg flex items-center justify-center space-x-2 shadow-md transition">
                            <i class="fas <?php echo $isPaused ? 'fa-play' : 'fa-pause'; ?>" id="pauseResumeIcon"></i>
                            <span id="pauseResumeText"><?php echo $isPaused ? 'RESUME QUEUE' : 'PAUSE QUEUE'; ?></span>
                        </button>
                    </div>
                </div>

                <!-- Right Panel - Queue Lists -->
                <div class="lg:col-span-3 space-y-6">
                    <!-- Queue List -->
                    <div class="bg-white border border-gray-200 rounded-lg">
                        <div class="flex justify-between items-center px-5 py-3 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-blue-900">Queue List</h3>
                            <div class="bg-blue-900 text-white rounded-full w-8 h-8 flex items-center justify-center text-xs font-semibold">
                                <?php echo count($waitingQueues) + count($stalledQueues) + count($skippedQueues); ?>
                            </div>
                        </div>
                        
                        <!-- Active Queue -->
                        <div class="border-b border-gray-200">
                            <button class="group flex justify-between items-center w-full px-5 py-3 bg-blue-50 focus:outline-none" onclick="toggleQueueSection('activeQueue')">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-users text-blue-600 w-4 h-4"></i>
                                    <h4 class="font-semibold text-blue-900 text-sm">Active Queue</h4>
                                    <div class="bg-blue-900 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-semibold">
                                        <?php echo count($waitingQueues); ?>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-down text-blue-900 w-4 h-4 transition-transform" id="activeQueue-arrow"></i>
                            </button>
                            <div id="activeQueue-content" class="divide-y divide-gray-200">
                                <?php if (empty($waitingQueues)): ?>
                                <div class="px-5 py-8 text-center text-gray-500">
                                    <i class="fas fa-users text-3xl mb-2"></i>
                                    <p>No active queue items</p>
                                </div>
                                <?php else: ?>
                                <?php foreach ($waitingQueues as $queue): ?>
                                <div class="px-5 py-3 hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="flex items-center space-x-2">
                                                <?php if ($queue['queue_type'] === 'priority'): ?>
                                                <i class="fas fa-star text-yellow-500 text-xs"></i>
                                                <?php endif; ?>
                                                <span class="font-bold text-gray-900"><?php echo htmlspecialchars($queue['queue_number']); ?></span>
                                            </div>
                                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($queue['student_name']); ?></p>
                                        </div>
                                        <span class="text-xs text-gray-500">
                                        <?php 
                                        // Convert queue time from UTC to Manila
                                        $time = new DateTime($queue['created_at'], new DateTimeZone('UTC'));
                                        $time->setTimezone(new DateTimeZone('Asia/Manila'));
                                        echo $time->format('g:i A');
                                        ?>
                                    </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Stalled Queue -->
                        <div class="border-b border-gray-200">
                            <button class="group flex justify-between items-center w-full px-5 py-3 bg-yellow-50 focus:outline-none" onclick="toggleQueueSection('stalledQueue')">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-exclamation-triangle text-yellow-500 w-4 h-4"></i>
                                    <h4 class="font-semibold text-yellow-600 text-sm">Stalled Queue</h4>
                                    <div class="bg-yellow-400 text-yellow-900 rounded-full w-6 h-6 flex items-center justify-center text-xs font-semibold">
                                        <?php echo count($stalledQueues); ?>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-down text-yellow-600 w-4 h-4 transition-transform" id="stalledQueue-arrow"></i>
                            </button>
                            <div id="stalledQueue-content" class="divide-y divide-gray-200 hidden">
                                <?php if (empty($stalledQueues)): ?>
                                <div class="px-5 py-8 text-center text-gray-500">
                                    <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                                    <p>No stalled queue items</p>
                                </div>
                                <?php else: ?>
                                <?php foreach ($stalledQueues as $queue): ?>
                                <div class="px-5 py-3 hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($queue['queue_number']); ?></span>
                                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($queue['student_name']); ?></p>
                                        </div>
                                        <button onclick="resumeQueue(<?php echo $queue['id']; ?>)" 
                                                <?php echo $isPaused ? 'disabled' : ''; ?>
                                                class="text-xs <?php echo $isPaused ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'; ?> text-white px-2 py-1 rounded">
                                            Resume
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Skipped Queue -->
                        <div class="border-b border-red-200">
                            <button class="group flex justify-between items-center w-full px-5 py-3 bg-red-50 focus:outline-none" onclick="toggleQueueSection('skippedQueue')">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-times-circle text-red-600 w-4 h-4"></i>
                                    <h4 class="font-semibold text-red-600 text-sm">Skipped Queue</h4>
                                    <div class="bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-semibold">
                                        <?php echo count($skippedQueues); ?>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-down text-red-600 w-4 h-4 transition-transform" id="skippedQueue-arrow"></i>
                            </button>
                            <div id="skippedQueue-content" class="divide-y divide-gray-200 hidden">
                                <?php if (empty($skippedQueues)): ?>
                                <div class="px-5 py-8 text-center text-gray-500">
                                    <i class="fas fa-times-circle text-3xl mb-2"></i>
                                    <p>No skipped queue items</p>
                                </div>
                                <?php else: ?>
                                <?php foreach ($skippedQueues as $queue): ?>
                                <div class="px-5 py-3 hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($queue['queue_number']); ?></span>
                                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($queue['student_name']); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Transaction Status -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Today's Transaction Status</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-clock text-blue-600 text-2xl mb-2"></i>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $avgServiceTime; ?> min</p>
                                <p class="text-sm text-gray-600">Avg Service Time</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-check-circle text-green-600 text-2xl mb-2"></i>
                                <p class="text-2xl font-bold text-gray-900"><?php echo $completedCount; ?></p>
                                <p class="text-sm text-gray-600">Completed</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-pause-circle text-yellow-600 text-2xl mb-2"></i>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($stalledQueues); ?></p>
                                <p class="text-sm text-gray-600">Stalled</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-times-circle text-red-600 text-2xl mb-2"></i>
                                <p class="text-2xl font-bold text-gray-900"><?php echo count($skippedQueues); ?></p>
                                <p class="text-sm text-gray-600">Skipped</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include '../../Footer.php'; ?>
    
    <script>
    let currentQueueId = <?php echo $currentQueue ? $currentQueue['id'] : 'null'; ?>;
    let isPaused = <?php echo $isPaused ? 'true' : 'false'; ?>;
    let queueCreatedAt = <?php echo $currentQueueDetails ? "'" . $currentQueueDetails['created_at'] . "'" : 'null'; ?>;
    
    // Toggle queue section
    function toggleQueueSection(sectionId) {
        const content = document.getElementById(sectionId + '-content');
        const arrow = document.getElementById(sectionId + '-arrow');
        
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            arrow.style.transform = 'rotate(180deg)';
        } else {
            content.classList.add('hidden');
            arrow.style.transform = 'rotate(0deg)';
        }
    }
    
    // Update current time every second
    function updateCurrentTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        });
        document.getElementById('currentTime').textContent = timeString;
    }
    
    // Update total wait time every second
    function updateTotalWaitTime() {
        if (queueCreatedAt) {
            const createdAt = new Date(queueCreatedAt);
            const now = new Date();
            const diffMs = now - createdAt;
            
            const hours = Math.floor(diffMs / (1000 * 60 * 60));
            const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diffMs % (1000 * 60)) / 1000);
            
            let timeString = '';
            if (hours > 0) {
                timeString = `${hours}h ${minutes}m ${seconds}s`;
            } else if (minutes > 0) {
                timeString = `${minutes}m ${seconds}s`;
            } else {
                timeString = `${seconds}s`;
            }
            
            document.getElementById('totalWaitTime').textContent = timeString;
        }
    }
    
    // Complete queue and move to next
    function callNextQueue() {
        if (isPaused) {
            alert('Queue system is paused. Please resume first.');
            return;
        }

        if (currentQueueId) {
            performQueueAction('complete', currentQueueId);
        } else {
            performQueueAction('next', null);
        }
    }
    
    // Stall queue
    function stallQueue() {
        if (isPaused) {
            alert('Queue system is paused. Please resume first.');
            return;
        }

        if (!currentQueueId) {
            alert('No queue is currently being served');
            return;
        }

        performQueueAction('stall', currentQueueId);
    }
    
    // Skip queue
    function skipQueue() {
        if (isPaused) {
            alert('Queue system is paused. Please resume first.');
            return;
        }

        if (!currentQueueId) {
            alert('No queue is currently being served');
            return;
        }

        performQueueAction('skip', currentQueueId);
    }
    
    // Toggle pause/resume functionality
    function togglePauseResume() {
        performQueueAction('toggle_pause', null);
    }
    
    // Resume stalled queue
    function resumeQueue(queueId) {
        if (isPaused) {
            alert('Queue system is paused. Please resume first.');
            return;
        }

        performQueueAction('resume', queueId);
    }
    
    // Generic function to perform queue actions via AJAX
function performQueueAction(action, queueId) {
    // Show loading state on buttons
    const buttons = document.querySelectorAll('button');
    buttons.forEach(btn => btn.disabled = true);
    
    // Prepare the request
    const formData = new FormData();
    formData.append('action', action);
    if (queueId) {
        formData.append('queue_id', queueId);
    }
    
    // Use POST for actions, GET for fetching data
    const method = (action === 'get_current_queue') ? 'GET' : 'POST';
    const url = (action === 'get_current_queue') ? `queue_api.php?action=${action}` : 'queue_api.php';
    
    // Perform AJAX request
    fetch(url, {
        method: method,
        body: method === 'POST' ? formData : undefined
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Success - update the page data
                if (action === 'toggle_pause') {
                    // For pause/resume, reload the page to update the session state
                    location.reload();
                } else {
                    loadQueueData();
                }
            } else {
                // Error - show alert
                if (!window.isLoggingOut) {
                    alert('Error: ' + (data.error || 'Unknown error occurred'));
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (!window.isLoggingOut) {
                alert('Error performing queue action. Please try again.');
            }
        })
        .finally(() => {
            // Re-enable buttons after a short delay (only if not reloading)
            if (action !== 'toggle_pause') {
                setTimeout(() => {
                    buttons.forEach(btn => btn.disabled = false);
                }, 1000);
            }
        });
}

// Load queue data from backend using queue_api.php
function loadQueueData() {
    fetch('queue_api.php?action=get_current_queue')
        .then(response => {
            // If unauthorized (401), just load empty data
            if (response.status === 401) {
                console.warn('Unauthorized (401) from queue_api.php, loading empty queue data');
                loadEmptyData();
                return null;
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                updateCurrentQueue(data.currentQueue);
                updateQueueList(data.waitingQueues || [], data.stalledQueues || [], data.skippedQueues || []);
                updateStatistics(data.statistics);
            } else {
                loadEmptyData();
            }
        })
        .catch(error => {
            console.error('Error loading queue data:', error);
            loadEmptyData();
        });
}

// Load empty data when no backend connection
function loadEmptyData() {
    const emptyData = {
        currentQueue: null,
        queueList: [],
        statistics: {
            avgServiceTime: "--",
            completed: 0,
            stalled: 0,
            cancelled: 0
        }
    };
    
    updateCurrentQueue(emptyData.currentQueue);
    updateQueueList(emptyData.queueList, [], []);
    updateStatistics(emptyData.statistics);
}

// Update current queue display
function updateCurrentQueue(queue) {
    if (queue) {
        currentQueueId = queue.id;
        queueCreatedAt = queue.created_at;
        
        // Update queue number display
        const queueNumberElement = document.getElementById('currentQueueNumber');
        if (queueNumberElement) {
            queueNumberElement.textContent = queue.queue_number || '--';
            queueNumberElement.className = 'text-6xl font-bold text-yellow-400 mb-3';
        }
        
        // Update student information
        document.getElementById('studentName').textContent = queue.student_name || '--';
        document.getElementById('studentId').textContent = queue.student_id || '--';
        document.getElementById('studentCourse').textContent = queue.course_program || '--';
        document.getElementById('studentYear').textContent = queue.year_level || '--';
        document.getElementById('timeRequested').textContent = queue.time_requested || '--';
        
        // Update priority type
        const priorityType = document.getElementById('priorityType');
        if (priorityType) {
            if (queue.queue_type === 'priority') {
                priorityType.innerHTML = '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-yellow-200 text-gray-800"><i class="fas fa-star mr-2 text-black"></i>Priority</span>';
            } else {
                priorityType.innerHTML = '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-gray-200 text-gray-800">Regular</span>';
            }
        }
    } else {
        // Clear display
        currentQueueId = null;
        queueCreatedAt = null;
        const queueNumberElement = document.getElementById('currentQueueNumber');
        if (queueNumberElement) {
            queueNumberElement.textContent = '--';
            queueNumberElement.className = 'text-6xl font-bold text-gray-300 mb-3';
        }
        
        document.getElementById('studentName').textContent = '--';
        document.getElementById('studentId').textContent = '--';
        document.getElementById('studentCourse').textContent = '--';
        document.getElementById('studentYear').textContent = '--';
        document.getElementById('timeRequested').textContent = '--';
        document.getElementById('totalWaitTime').textContent = '--';
    }
}

// Update queue list
function updateQueueList(waitingQueues, stalledQueues, skippedQueues) {
    // Update counts
    const totalCount = document.querySelector('.bg-blue-900.text-white.rounded-full.w-8.h-8');
    const activeCount = document.querySelector('.bg-blue-900.text-white.rounded-full.w-6.h-6');
    const stalledCount = document.querySelector('.bg-yellow-400.text-yellow-900.rounded-full.w-6.h-6');
    const skippedCount = document.querySelector('.bg-red-500.text-white.rounded-full.w-6.h-6');
    
    const total = waitingQueues.length + stalledQueues.length + skippedQueues.length;
    if (totalCount) totalCount.textContent = total;
    if (activeCount) activeCount.textContent = waitingQueues.length;
    if (stalledCount) stalledCount.textContent = stalledQueues.length;
    if (skippedCount) skippedCount.textContent = skippedQueues.length;
    
    // Update active queue content
    updateActiveQueue(waitingQueues);
    
    // Update stalled queue content
    updateStalledQueue(stalledQueues);
    
    // Update skipped queue content
    updateSkippedQueue(skippedQueues);
}

// Update statistics
function updateStatistics(stats) {
    const statElements = document.querySelectorAll('.bg-gray-50 .text-2xl.font-bold.text-gray-900');
    
    if (statElements.length >= 4) {
        statElements[0].textContent = stats.avgServiceTime || '--';
        statElements[1].textContent = stats.completed || 0;
        statElements[2].textContent = stats.stalled || 0;
        statElements[3].textContent = stats.cancelled || 0;
    }
}

    
    // Update active queue list
    function updateActiveQueue(queues) {
        const activeQueueContent = document.getElementById('activeQueue-content');
        
        if (queues.length === 0) {
            activeQueueContent.innerHTML = `
                <div class="px-5 py-8 text-center text-gray-500">
                    <i class="fas fa-users text-3xl mb-2"></i>
                    <p>No active queue items</p>
                </div>
            `;
        } else {
            let html = '';
            queues.forEach(queue => {
                const queueTime = new Date(queue.created_at).toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
                
                html += `
                <div class="px-5 py-3 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center space-x-2">
                                ${queue.queue_type === 'priority' ? '<i class="fas fa-star text-yellow-500 text-xs"></i>' : ''}
                                <span class="font-bold text-gray-900">${queue.queue_number}</span>
                            </div>
                            <p class="text-xs text-gray-600">${queue.student_name}</p>
                        </div>
                        <span class="text-xs text-gray-500">${queueTime}</span>
                    </div>
                </div>
                `;
            });
            activeQueueContent.innerHTML = html;
        }
    }
    
    // Update stalled queue list
    function updateStalledQueue(queues) {
        const stalledQueueContent = document.getElementById('stalledQueue-content');
        
        if (queues.length === 0) {
            stalledQueueContent.innerHTML = `
                <div class="px-5 py-8 text-center text-gray-500">
                    <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                    <p>No stalled queue items</p>
                </div>
            `;
        } else {
            let html = '';
            queues.forEach(queue => {
                html += `
                <div class="px-5 py-3 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold text-gray-900">${queue.queue_number}</span>
                            <p class="text-xs text-gray-600">${queue.student_name}</p>
                        </div>
                        <button onclick="resumeQueue(${queue.id})" 
                                ${isPaused ? 'disabled' : ''}
                                class="text-xs ${isPaused ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'} text-white px-2 py-1 rounded">
                            Resume
                        </button>
                    </div>
                </div>
                `;
            });
            stalledQueueContent.innerHTML = html;
        }
    }
    
    // Update skipped queue list
    function updateSkippedQueue(queues) {
        const skippedQueueContent = document.getElementById('skippedQueue-content');
        
        if (queues.length === 0) {
            skippedQueueContent.innerHTML = `
                <div class="px-5 py-8 text-center text-gray-500">
                    <i class="fas fa-times-circle text-3xl mb-2"></i>
                    <p>No skipped queue items</p>
                </div>
            `;
        } else {
            let html = '';
            queues.forEach(queue => {
                html += `
                <div class="px-5 py-3 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-bold text-gray-900">${queue.queue_number}</span>
                            <p class="text-xs text-gray-600">${queue.student_name}</p>
                        </div>
                    </div>
                </div>
                `;
            });
            skippedQueueContent.innerHTML = html;
        }
    }
    
    // Update queue counts
    function updateQueueCounts(activeQueues, stalledQueues, skippedQueues) {
        // Update total count
        const totalCount = activeQueues.length + stalledQueues.length + skippedQueues.length;
        const totalCountElement = document.querySelector('.bg-blue-900.text-white.rounded-full.w-8.h-8');
        if (totalCountElement) {
            totalCountElement.textContent = totalCount;
        }
        
        // Update active queue count
        const activeCountElement = document.querySelector('.bg-blue-900.text-white.rounded-full.w-6.h-6');
        if (activeCountElement) {
            activeCountElement.textContent = activeQueues.length;
        }
        
        // Update stalled queue count
        const stalledCountElement = document.querySelector('.bg-yellow-400.text-yellow-900.rounded-full.w-6.h-6');
        if (stalledCountElement) {
            stalledCountElement.textContent = stalledQueues.length;
        }
        
        // Update skipped queue count
        const skippedCountElement = document.querySelector('.bg-red-500.text-white.rounded-full.w-6.h-6');
        if (skippedCountElement) {
            skippedCountElement.textContent = skippedQueues.length;
        }
    }
    
    // Initialize the interface
    document.addEventListener('DOMContentLoaded', function() {
        loadQueueData();
    });
    
    // Initialize real-time updates
    updateCurrentTime();
    updateTotalWaitTime();
    setInterval(updateCurrentTime, 1000);
    setInterval(updateTotalWaitTime, 1000);
    
    // Auto-refresh queue data every 30 seconds
    setInterval(loadQueueData, 30000);
    
    // Release counter assignment when page unloads (browser close, tab close, navigation away)
    window.addEventListener('beforeunload', function() {
        // Use sendBeacon API to reliably send the request even when the page is unloading
        // This ensures the counter assignment is released with released_at timestamp
        const formData = new FormData();
        formData.append('action', 'release_counter');
        
        // sendBeacon is specifically designed for sending data during page unload
        // It returns true if the request was successfully queued, false otherwise
        if (navigator.sendBeacon) {
            navigator.sendBeacon('queue_api.php', formData);
        } else if (window.fetch) {
            // Fallback to fetch with keepalive for browsers that don't support sendBeacon
            // keepalive ensures the request continues even after the page unloads
            try {
                fetch('queue_api.php', {
                    method: 'POST',
                    body: formData,
                    keepalive: true
                });
            } catch (e) {
                console.error('Failed to release counter assignment:', e);
            }
        }
    });
</script>
</body>
</html>