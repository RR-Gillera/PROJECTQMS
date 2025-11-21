<?php
session_start();
require_once __DIR__ . '/../../Student/db_config.php';
require_once __DIR__ . '/../admin_functions.php';

include_once __DIR__ . '/auto_cancel_trigger.php';

// Get database connection
$conn = getDBConnection();

// Get all dashboard data
$stats = getQueueStatistics($conn);

// Modified: Get recent activity (last 5 entries regardless of status, ordered by most recent)
$recentActivityQuery = "
    SELECT 
        q.id,
        q.queue_number,
        q.student_name,
        q.student_id,
        q.queue_type,
        q.status,
        q.created_at,
        q.served_at,
        q.completed_at,
        COUNT(qs.id) as services_count
    FROM queues q
    LEFT JOIN queue_services qs ON q.id = qs.queue_id
    WHERE DATE(q.created_at) = CURDATE()
    GROUP BY q.id
    ORDER BY 
        CASE 
            WHEN q.status = 'serving' THEN 1
            WHEN q.status = 'waiting' THEN 2
            ELSE 3
        END,
        q.updated_at DESC,
        q.created_at DESC
    LIMIT 5
";
$recentActivityResult = $conn->query($recentActivityQuery);
$recentActivity = [];
while ($row = $recentActivityResult->fetch_assoc()) {
    $recentActivity[] = $row;
}

$currentlyServing = getCurrentlyServing($conn);

// Get top services
$topServicesQuery = "
    SELECT qs.service_name, COUNT(*) as count
    FROM queue_services qs
    JOIN queues q ON qs.queue_id = q.id
    WHERE DATE(q.created_at) = CURDATE()
    GROUP BY qs.service_name
    ORDER BY count DESC
    LIMIT 5
";
$topServicesResult = $conn->query($topServicesQuery);
$topServices = [];
while ($row = $topServicesResult->fetch_assoc()) {
    $topServices[] = $row;
}

// Get extended queue statistics - FIXED to use 'serving' instead of 'in_service'
$extendedStatsQuery = "
    SELECT 
        status,
        COUNT(*) as count
    FROM queues
    WHERE DATE(created_at) = CURDATE()
    GROUP BY status
";
$extendedStatsResult = $conn->query($extendedStatsQuery);
$extendedStats = [];
while ($row = $extendedStatsResult->fetch_assoc()) {
    $extendedStats[$row['status']] = $row['count'];
}

// Set defaults for missing statuses - FIXED
$queueStatuses = ['waiting', 'completed', 'skipped', 'serving', 'stalled', 'cancelled'];
foreach ($queueStatuses as $status) {
    if (!isset($extendedStats[$status])) {
        $extendedStats[$status] = 0;
    }
}

// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SeQueueR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .stat-card {
            transition: transform 0.2s ease-in-out;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .service-bar {
            transition: width 0.3s ease-in-out;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Admin Header -->
    <?php include 'Header.php'; ?>
    
    <!-- Main Content -->
    <main class="min-h-screen">
        <div class="py-8 px-6 md:px-10 mx-4 md:mx-8 lg:mx-12">
            <!-- Summary Cards Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Queues Today -->
                <div class="stat-card bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-list text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_today']; ?></p>
                            <p class="text-sm text-gray-600">Total Queues Today</p>
                        </div>
                    </div>
                </div>

                <!-- Currently Serving -->
                <div class="stat-card bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-3xl font-bold text-gray-900">
                                <?php 
                                echo $stats['serving'] > 0 
                                    ? (isset($currentlyServing[0]) ? $currentlyServing[0]['queue_number'] : '--')
                                    : '--';
                                ?>
                            </p>
                            <p class="text-sm text-gray-600">Currently Serving</p>
                            <p class="text-xs text-gray-500">
                                <?php 
                                echo $stats['serving'] > 0 && isset($currentlyServing[0]['window_number'])
                                    ? 'Counter ' . $currentlyServing[0]['window_number']
                                    : 'No active counter';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Completed Queues -->
                <div class="stat-card bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-3xl font-bold text-gray-900"><?php echo $stats['completed']; ?></p>
                            <p class="text-sm text-gray-600">Completed Queues</p>
                        </div>
                    </div>
                </div>

                <!-- Pending Queues -->
                <div class="stat-card bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-3xl font-bold text-gray-900"><?php echo $stats['waiting']; ?></p>
                            <p class="text-sm text-gray-600">Pending Queues</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Queue Status Overview -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Queue Status Overview</h3>
                        <div class="flex flex-col lg:flex-row lg:items-center lg:space-x-8">
                            <!-- Pie Chart -->
                            <div class="flex-1 mb-6 lg:mb-0">
                                <canvas id="queueStatusChart" width="300" height="300"></canvas>
                            </div>
                            
                            <!-- Legend -->
                            <div class="flex-1">
                                <div class="space-y-3" id="queueStatusLegend">
                                    <!-- Will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                            <a href="History.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</a>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Queue #</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Services</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($recentActivity)): ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                            No recent activity
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($recentActivity as $activity): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <?php if ($activity['queue_type'] === 'priority'): ?>
                                                <i class="fas fa-star text-yellow-500 mr-2"></i>
                                                <?php endif; ?>
                                                <span class="text-sm font-medium text-blue-600"><?php echo htmlspecialchars($activity['queue_number']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($activity['student_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($activity['student_id']); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="text-sm text-gray-900"><?php echo $activity['services_count']; ?> service(s)</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <?php
                                            $statusColors = [
                                                'waiting' => 'bg-yellow-100 text-yellow-800',
                                                'serving' => 'bg-purple-100 text-purple-800',
                                                'completed' => 'bg-green-100 text-green-800',
                                                'skipped' => 'bg-red-100 text-red-800',
                                                'stalled' => 'bg-orange-100 text-orange-800',
                                                'cancelled' => 'bg-gray-100 text-gray-800'
                                            ];
                                            $statusColor = $statusColors[$activity['status']] ?? 'bg-gray-100 text-gray-800';
                                            $statusText = ucfirst(str_replace('_', ' ', $activity['status']));
                                            ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusColor; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                            <?php 
                                            // Show the most relevant time based on status
                                            $displayTime = $activity['created_at'];
                                            if ($activity['status'] === 'completed' && $activity['completed_at']) {
                                                $displayTime = $activity['completed_at'];
                                            } elseif ($activity['status'] === 'serving' && $activity['served_at']) {
                                                $displayTime = $activity['served_at'];
                                            }
                                            
                                            $time = new DateTime($displayTime);
                                            echo $time->format('g:i A');
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-8">
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Quick Actions</h3>
                        <div class="space-y-3">
                            <a href="Queue.php" class="w-full flex items-center justify-center space-x-2 px-4 py-3 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50 transition-colors">
                                <i class="fas fa-list"></i>
                                <span>Manage Queues</span>
                            </a>
                            <a href="User.php" class="w-full flex items-center justify-center space-x-2 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus"></i>
                                <span>Add Account</span>
                            </a>
                            <button onclick="window.location.href='History.php'" class="w-full flex items-center justify-center space-x-2 px-4 py-3 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50 transition-colors">
                                <i class="fas fa-history"></i>
                                <span>View History</span>
                            </button>
                        </div>
                    </div>

                    <!-- Top Services Today -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">Top Services Today</h3>
                        <div class="space-y-4">
                            <?php if (empty($topServices)): ?>
                            <p class="text-gray-500 text-sm">No services data available</p>
                            <?php else: ?>
                            <?php 
                            $maxCount = max(array_column($topServices, 'count'));
                            foreach ($topServices as $service): 
                            ?>
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($service['service_name']); ?></span>
                                        <span class="text-sm font-bold text-gray-900"><?php echo $service['count']; ?></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="service-bar bg-blue-600 h-2 rounded-full" style="width: <?php echo ($service['count'] / $maxCount) * 100; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- System Status -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-6">System Status</h3>
                        <div class="space-y-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                <span class="text-sm font-medium text-gray-900">System Online</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                <span class="text-sm font-medium text-gray-900">Queue System Active</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-4">
                                Last updated: <span id="lastUpdatedTime"><?php echo date('g:i A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Include Footer -->
    <?php include '../../Footer.php'; ?>
    
    <script>
        // Dashboard data from PHP - FIXED
        const dashboardData = {
            queueStatus: {
                waiting: <?php echo $extendedStats['waiting']; ?>,
                completed: <?php echo $extendedStats['completed']; ?>,
                skipped: <?php echo $extendedStats['skipped']; ?>,
                serving: <?php echo $extendedStats['serving']; ?>,
                stalled: <?php echo $extendedStats['stalled']; ?>,
                cancelled: <?php echo $extendedStats['cancelled']; ?>
            }
        };
        
        let queueStatusChart = null;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateQueueStatusChart();
            updateLastUpdatedTime();
            
            // Auto-refresh every 30 seconds
            setInterval(() => location.reload(), 30000);
            setInterval(updateLastUpdatedTime, 60000);
        });
        
        // Update queue status chart
        function updateQueueStatusChart() {
            const ctx = document.getElementById('queueStatusChart').getContext('2d');
            
            const data = [
                dashboardData.queueStatus.waiting,
                dashboardData.queueStatus.completed,
                dashboardData.queueStatus.skipped,
                dashboardData.queueStatus.serving,
                dashboardData.queueStatus.stalled,
                dashboardData.queueStatus.cancelled
            ];
            
            const total = data.reduce((sum, value) => sum + value, 0);
            
            queueStatusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Waiting', 'Completed', 'Skipped', 'Serving', 'Stalled', 'Cancelled'],
                    datasets: [{
                        data: data,
                        backgroundColor: ['#3B82F6', '#10B981', '#9CA3AF', '#000000', '#FBBF24', '#EF4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            updateQueueStatusLegend(data, total);
        }
        
        // Update legend
        function updateQueueStatusLegend(data, total) {
            const legend = document.getElementById('queueStatusLegend');
            const labels = ['Waiting', 'Completed', 'Skipped', 'Serving', 'Stalled', 'Cancelled'];
            const colors = ['#3B82F6', '#10B981', '#9CA3AF', '#000000', '#FBBF24', '#EF4444'];
            
            legend.innerHTML = labels.map((label, index) => {
                const value = data[index];
                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                return `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 rounded" style="background-color: ${colors[index]}"></div>
                            <span class="text-sm text-gray-700">${label}</span>
                        </div>
                        <span class="text-sm font-medium text-gray-900">${value} (${percentage}%)</span>
                    </div>
                `;
            }).join('');
        }
        
        // Update time
        function updateLastUpdatedTime() {
            const now = new Date();
            document.getElementById('lastUpdatedTime').textContent = now.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
        }
    </script>
</body>
</html>