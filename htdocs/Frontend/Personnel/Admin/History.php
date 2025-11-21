<?php
session_start();
require_once __DIR__ . '/../../Student/db_config.php';
require_once __DIR__ . '/../admin_functions.php';

include_once __DIR__ . '/auto_cancel_trigger.php';

// Get database connection
$conn = getDBConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$dateRange = $_GET['date_range'] ?? '';
$status = $_GET['status'] ?? '';
$service = $_GET['service'] ?? '';

// Counter filter (admin can filter by counter or see all)
$counterFilter = $_GET['counter'] ?? '';

// Build query
$where = ["DATE(q.created_at) <= CURDATE()"];
$params = [];
$types = '';

// Admin can filter by counter (show all if empty)
if ($counterFilter && $counterFilter !== 'all') {
    $where[] = "q.counter_id = ?";
    $params[] = (int)$counterFilter;
    $types .= 'i';
}

if ($search) {
    $where[] = "(q.queue_number LIKE ? OR q.student_name LIKE ? OR q.student_id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if ($status) {
    $where[] = "q.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($dateRange) {
    switch ($dateRange) {
        case 'today':
            $where[] = "DATE(q.created_at) = CURDATE()";
            break;
        case 'week':
            $where[] = "YEARWEEK(q.created_at) = YEARWEEK(NOW())";
            break;
        case 'month':
            $where[] = "MONTH(q.created_at) = MONTH(NOW()) AND YEAR(q.created_at) = YEAR(NOW())";
            break;
    }
}

$whereClause = implode(' AND ', $where);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM queues q WHERE $whereClause";
if ($types) {
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
} else {
    $countResult = $conn->query($countQuery);
}
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Get transactions
$query = "
    SELECT 
        q.*,
        GROUP_CONCAT(qs.service_name SEPARATOR ', ') as services
    FROM queues q
    LEFT JOIN queue_services qs ON q.id = qs.queue_id
    WHERE $whereClause
    GROUP BY q.id
    ORDER BY q.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - SeQueueR</title>
    <link rel="icon" type="image/png" href="/Frontend/favicon.php">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'Header.php'; ?>
    
    <main class="bg-gray-50 min-h-screen">
        <div class="py-8 px-6 md:px-10 mx-4 md:mx-8 lg:mx-12">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Transaction History</h1>
                    <p class="text-gray-600 mt-2">View and manage your past queue transactions</p>
                </div>
                
                <!-- Export Button -->
                <div class="mt-4 sm:mt-0">
                    <div class="relative" id="exportDropdown">
                        <button id="exportBtn" 
                                class="border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-semibold py-2 px-4 rounded-lg flex items-center space-x-2 <?php echo empty($transactions) ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                                onclick="<?php echo !empty($transactions) ? 'toggleExportDropdown(event)' : ''; ?>"
                                <?php echo empty($transactions) ? 'disabled' : ''; ?>>
                            <i class="fas fa-download"></i>
                            <span>Export</span>
                            <i class="fas fa-chevron-down ml-1"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div id="exportMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-10">
                            <div class="py-2">
                                <div class="px-4 py-2 text-sm font-medium text-gray-700 border-b border-gray-100">
                                    Export Format
                                </div>
                                <div class="py-1">
                                    <button onclick="exportToFormat('pdf')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-3">
                                        <div class="w-6 h-6 bg-red-500 rounded flex items-center justify-center">
                                            <span class="text-white text-xs font-bold">PDF</span>
                                        </div>
                                        <span>PDF Document</span>
                                    </button>
                                    <button onclick="exportToFormat('excel')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-3">
                                        <div class="w-6 h-6 bg-green-500 rounded flex items-center justify-center">
                                            <span class="text-white text-xs font-bold">XLS</span>
                                        </div>
                                        <span>Excel Spreadsheet</span>
                                    </button>
                                    <button onclick="exportToFormat('csv')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-3">
                                        <div class="w-6 h-6 bg-blue-500 rounded flex items-center justify-center">
                                            <span class="text-white text-xs font-bold">CSV</span>
                                        </div>
                                        <span>CSV File</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <form method="GET" action="History.php" class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:space-x-4 space-y-4 lg:space-y-0">
                    <!-- Search Input -->
                    <div class="flex-1">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Search by queue number, student name, or ID...">
                        </div>
                    </div>
                    
                    <!-- Filter Dropdowns -->
                    <div class="flex flex-col sm:flex-row sm:space-x-4 space-y-4 sm:space-y-0">
                        <select name="counter" class="appearance-none bg-white border border-gray-300 rounded-md px-3 py-2 pr-8 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="all" <?php echo ($counterFilter === '' || $counterFilter === 'all') ? 'selected' : ''; ?>>All Counters</option>
                            <option value="1" <?php echo $counterFilter === '1' ? 'selected' : ''; ?>>Counter 1</option>
                            <option value="2" <?php echo $counterFilter === '2' ? 'selected' : ''; ?>>Counter 2</option>
                            <option value="3" <?php echo $counterFilter === '3' ? 'selected' : ''; ?>>Counter 3</option>
                            <option value="4" <?php echo $counterFilter === '4' ? 'selected' : ''; ?>>Counter 4</option>
                        </select>
                        
                        <select name="date_range" class="appearance-none bg-white border border-gray-300 rounded-md px-3 py-2 pr-8 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Time</option>
                            <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $dateRange === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                        
                        <select name="status" class="appearance-none bg-white border border-gray-300 rounded-md px-3 py-2 pr-8 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Statuses</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="stalled" <?php echo $status === 'stalled' ? 'selected' : ''; ?>>Stalled</option>
                            <option value="skipped" <?php echo $status === 'skipped' ? 'selected' : ''; ?>>Skipped</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md flex items-center space-x-2">
                            <i class="fas fa-filter"></i>
                            <span>Filter</span>
                        </button>
                        
                        <a href="History.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-md flex items-center space-x-2">
                            <i class="fas fa-sync-alt"></i>
                            <span>Clear</span>
                        </a>
                        
                        <input type="hidden" name="counter" value="<?php echo htmlspecialchars($counterFilter); ?>">
                    </div>
                </div>
            </form>

            <!-- Transaction Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Queue #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Counter</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wait Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-history text-gray-300 text-4xl mb-4"></i>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">No transactions found</h3>
                                        <p class="text-gray-500">Try adjusting your filters or search terms</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php if ($transaction['queue_type'] === 'priority'): ?>
                                        <i class="fas fa-star text-yellow-500 mr-2"></i>
                                        <?php endif; ?>
                                        <span class="text-sm font-medium text-blue-600"><?php echo htmlspecialchars($transaction['queue_number']); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($transaction['student_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($transaction['student_id']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($transaction['counter_id']): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Counter <?php echo htmlspecialchars($transaction['counter_id']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-400">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php 
                                        $services = $transaction['services'] ? explode(', ', $transaction['services']) : [];
                                        echo !empty($services) ? htmlspecialchars($services[0]) : 'N/A';
                                        if (count($services) > 1) {
                                            echo ' <span class="text-xs text-blue-600">+' . (count($services) - 1) . '</span>';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusColors = [
                                        'completed' => 'bg-green-100 text-green-800',
                                        'stalled' => 'bg-yellow-100 text-yellow-800',
                                        'skipped' => 'bg-red-100 text-red-800',
                                        'cancelled' => 'bg-gray-100 text-gray-800',
                                        'waiting' => 'bg-blue-100 text-blue-800',
                                        'serving' => 'bg-purple-100 text-purple-800'
                                    ];
                                    $statusColor = $statusColors[$transaction['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusColor; ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php 
                                    $datetime = new DateTime($transaction['created_at']);
                                    echo $datetime->format('M d, Y');
                                    ?>
                                    <div class="text-gray-500"><?php echo $datetime->format('g:i A'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php 
                                    if ($transaction['served_at'] && $transaction['created_at']) {
                                        $created = new DateTime($transaction['created_at']);
                                        $served = new DateTime($transaction['served_at']);
                                        $diff = $created->diff($served);
                                        echo $diff->h . 'h ' . $diff->i . 'm';
                                    } else {
                                        echo '--';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-6">
                <div class="text-sm text-gray-700 mb-4 sm:mb-0">
                    Showing <?php echo min($offset + 1, $totalRows); ?> to <?php echo min($offset + $limit, $totalRows); ?> of <?php echo $totalRows; ?> results
                </div>
                <div class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&date_range=<?php echo $dateRange; ?>&status=<?php echo $status; ?>&counter=<?php echo urlencode($counterFilter); ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        &lt; Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date_range=<?php echo $dateRange; ?>&status=<?php echo $status; ?>&counter=<?php echo urlencode($counterFilter); ?>" class="px-3 py-2 text-sm font-medium <?php echo $i === $page ? 'text-blue-600 bg-blue-50 border-blue-300' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50'; ?> border rounded-md">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&date_range=<?php echo $dateRange; ?>&status=<?php echo $status; ?>&counter=<?php echo urlencode($counterFilter); ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Next &gt;
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include '../../Footer.php'; ?>
    
    <script>
        // Toggle export dropdown
        function toggleExportDropdown(event) {
            event.preventDefault();
            event.stopPropagation();
            
            const menu = document.getElementById('exportMenu');
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
            } else {
                menu.classList.add('hidden');
            }
        }
        
        // Close export dropdown
        function closeExportDropdown() {
            document.getElementById('exportMenu').classList.add('hidden');
        }
        
        // Export to format
        function exportToFormat(format) {
            closeExportDropdown();
            
            // Build export URL with current filters
            const params = new URLSearchParams(window.location.search);
            params.set('format', format);
            
            // Open export in new window
            window.location.href = 'export_history.php?' + params.toString();
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const exportDropdown = document.getElementById('exportDropdown');
            if (!exportDropdown.contains(event.target)) {
                closeExportDropdown();
            }
        });
    </script>
</body>
</html>