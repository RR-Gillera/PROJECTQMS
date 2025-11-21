<?php
// Handle backend actions for Account Management (create user)
if (isset($_GET['action']) && $_GET['action'] === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure no previous output corrupts JSON
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { ob_end_clean(); }
    }
    header('Content-Type: application/json; charset=utf-8');

    require_once __DIR__ . '/../../../config.php';

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
        exit;
    }

    $fullName = trim($data['fullName'] ?? '');
    $idNumber = trim($data['idNumber'] ?? '');
    $course = trim($data['course'] ?? '');
    $yearLevel = trim($data['yearLevel'] ?? '');
    $email = trim($data['email'] ?? '');
    $mobile = trim($data['mobile'] ?? '');
    $password = (string)($data['password'] ?? '');

    if ($fullName === '' || $idNumber === '' || $course === '' || $yearLevel === '' || $email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit;
    }

    // Map to your DB schema
    // StudentID is int(8): sanitize to digits only
    $studentIdDigits = preg_replace('/\D+/', '', $idNumber);
    if ($studentIdDigits === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID Number must contain digits']);
        exit;
    }
    $studentId = (int)$studentIdDigits;

    // Password column is varchar(32): store MD5 (32 chars). Note: legacy.
    $passwordMd5 = md5($password);

    // Newly created accounts from this screen are **Working Scholars** by design.
    // Persist that explicitly in the `Role` column so they don't get treated as Admins.
    $defaultRole = 'Working';

    // Update table name here if different from `users`
    $table = 'Accounts';

    // Ensure a table with columns (FullName, StudentID, Course, YearLevel, Email, Password, Role) exists
    $stmt = $conn->prepare(
        "INSERT INTO {$table} (FullName, StudentID, Course, YearLevel, Email, Password, Role) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param('sisssss', $fullName, $studentId, $course, $yearLevel, $email, $passwordMd5, $defaultRole);

    if (!$stmt->execute()) {
        $error = $stmt->error ?: 'Insert failed';
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $error]);
        $stmt->close();
        exit;
    }

    $newId = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $newId,
            'idNumber' => $studentId,
            'name' => $fullName,
            'email' => $email,
            'course' => $course,
            'yearLevel' => $yearLevel,
            'mobile' => $mobile
        ]
    ]);
    exit;
}
// Handle backend action: list users
if (isset($_GET['action']) && $_GET['action'] === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { ob_end_clean(); } }
    header('Content-Type: application/json; charset=utf-8');

    require_once __DIR__ . '/../../../config.php';

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $sort = $_GET['sort'] ?? '';
    $direction = strtolower($_GET['direction'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
    
    // First check if LastActivity column exists for sorting
    $hasLastActivityColumn = false;
    $checkColumn = $conn->query("SHOW COLUMNS FROM Accounts LIKE 'LastActivity'");
    if ($checkColumn && $checkColumn->num_rows > 0) {
        $hasLastActivityColumn = true;
    }
    
    $allowedSort = [
        'idNumber' => 'StudentID',
        'name' => 'FullName',
        'email' => 'Email',
        'role' => 'Role',
        'status' => 'LastActivity'
    ];
    
    // Only allow sorting by LastActivity if column exists
    if (!$hasLastActivityColumn) {
        unset($allowedSort['status']);
        unset($allowedSort['lastActive']);
    } else {
        $allowedSort['lastActive'] = 'LastActivity';
    }
    
    $orderBy = $allowedSort[$sort] ?? 'FullName';

    $search = trim((string)($_GET['search'] ?? ''));
    $roleFilter = trim((string)($_GET['role'] ?? ''));
    $statusFilter = trim((string)($_GET['status'] ?? ''));

    $whereSql = '';
    $params = [];
    $types = '';
    $conditions = [];
    
    if ($search !== '') {
        $conditions[] = '(FullName LIKE ? OR Email LIKE ? OR CAST(StudentID AS CHAR) LIKE ?)';
        $like = '%' . $search . '%';
        $params = array_merge($params, [$like, $like, $like]);
        $types .= 'sss';
    }
    
    // Check if Role column exists before filtering by it
    $hasRoleColumn = false;
    $checkRoleColumn = $conn->query("SHOW COLUMNS FROM Accounts LIKE 'Role'");
    if ($checkRoleColumn && $checkRoleColumn->num_rows > 0) {
        $hasRoleColumn = true;
    }
    
    if ($roleFilter !== '' && $hasRoleColumn) {
        $conditions[] = 'Role = ?';
        $params[] = $roleFilter;
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $whereSql = 'WHERE ' . implode(' AND ', $conditions);
    }

    // Count total
    $countSql = "SELECT COUNT(*) AS cnt FROM Accounts $whereSql";
    $countStmt = $conn->prepare($countSql);
    if ($countStmt) {
        if ($types !== '') { $countStmt->bind_param($types, ...$params); }
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $row = $countRes ? $countRes->fetch_assoc() : ['cnt' => 0];
        $total = (int)($row['cnt'] ?? 0);
        $countStmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Count query failed: ' . $conn->error]);
        exit;
    }

    // If LastActivity column doesn't exist yet, try to create it
    if (!$hasLastActivityColumn) {
        // Try to create the LastActivity column if it doesn't exist
        $createColumn = @$conn->query("ALTER TABLE Accounts ADD COLUMN LastActivity DATETIME NULL DEFAULT NULL");
        if ($createColumn) {
            $hasLastActivityColumn = true;
        } else {
            // Check if error is because column already exists (some MySQL versions)
            $error = $conn->error;
            if (stripos($error, 'Duplicate column name') !== false || 
                stripos($error, 'already exists') !== false) {
                // Column exists, just verify
                $verify = $conn->query("SHOW COLUMNS FROM Accounts LIKE 'LastActivity'");
                if ($verify && $verify->num_rows > 0) {
                    $hasLastActivityColumn = true;
                }
            }
        }
    }
    
    // If status filter is active, fetch all records first, then filter and paginate in PHP
    $needsStatusFiltering = ($statusFilter !== '');
    $sqlLimit = $needsStatusFiltering ? '' : ' LIMIT ? OFFSET ?';
    
    // Build SQL query with or without LastActivity and Role
    $selectFields = "FullName, StudentID, Course, YearLevel, Email";
    if ($hasRoleColumn) {
        $selectFields .= ", Role";
    }
    if ($hasLastActivityColumn) {
        $selectFields .= ", LastActivity";
    }
    
    $sql = "SELECT $selectFields FROM Accounts $whereSql ORDER BY $orderBy $direction" . $sqlLimit;
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }

    if ($needsStatusFiltering) {
        // No limit/offset params when status filtering
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
    } else {
        // Normal pagination
        if ($types !== '') {
            $typesWithLimit = $types . 'ii';
            $paramsWithLimit = array_merge($params, [$limit, $offset]);
            $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
        } else {
            $stmt->bind_param('ii', $limit, $offset);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check for execution errors
    if ($stmt->error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Query execution failed: ' . $stmt->error]);
        exit;
    }
    
    $users = [];
    $autoId = 1; // Will be recalculated after filtering
    
    // Get current time for active/inactive calculation
    $currentTime = time();
    $activeThreshold = 300; // 5 minutes in seconds - user is considered active if LastActivity updated within this time
    
    // Try to get session path for checking active logins (fallback method)
    // Note: This may not work reliably in all online environments, so we prioritize LastActivity
    $activeUserSessions = [];
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sessionPath = session_save_path();
        if (empty($sessionPath) || !is_dir($sessionPath)) {
            $sessionPath = sys_get_temp_dir();
        }
        
        // Pre-load all active sessions to avoid checking files multiple times
        $sessionFiles = @glob($sessionPath . '/sess_*');
        if ($sessionFiles && is_array($sessionFiles)) {
            foreach ($sessionFiles as $sessionFile) {
                if (!is_file($sessionFile) || !is_readable($sessionFile)) {
                    continue;
                }
                // Check if session was modified recently (within threshold)
                $sessionAge = time() - @filemtime($sessionFile);
                if ($sessionAge < $activeThreshold) {
                    $sessionData = @file_get_contents($sessionFile);
                    if ($sessionData !== false) {
                        // Extract studentId from session data - handle different session formats
                        if (preg_match('/studentId["\']?\s*[|:]\s*s:(\d+):"(\d+)"/', $sessionData, $matches)) {
                            $activeUserSessions[$matches[2]] = true;
                        } elseif (preg_match('/"studentId":\s*"(\d+)"/', $sessionData, $matches)) {
                            $activeUserSessions[$matches[1]] = true;
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Session file checking failed - rely on LastActivity only
        // This is expected in many online environments
    }
    
    while ($r = $result->fetch_assoc()) {
        // Get role from database, default to 'Working' if null or empty or column doesn't exist
        $dbRole = '';
        if ($hasRoleColumn && isset($r['Role'])) {
            $dbRole = trim((string)$r['Role']);
        }
        $role = $dbRole !== '' ? $dbRole : 'Working';
        
        // Determine active/inactive status - prioritize LastActivity for online reliability
        $status = 'inactive';
        $lastActive = 'Never';
        $studentId = (string)$r['StudentID'];
        
        // PRIMARY METHOD: Check LastActivity timestamp (most reliable for online environments)
        if ($hasLastActivityColumn && isset($r['LastActivity'])) {
            $lastActivityValue = $r['LastActivity'];
            
            // Check if LastActivity is not null and not empty
            if ($lastActivityValue !== null && $lastActivityValue !== '') {
                // Handle both string and DateTime object formats
                if (is_string($lastActivityValue)) {
                    $lastActivityTime = strtotime($lastActivityValue);
                } elseif (is_object($lastActivityValue) && method_exists($lastActivityValue, 'format')) {
                    // DateTime object
                    $lastActivityTime = $lastActivityValue->getTimestamp();
                } else {
                    $lastActivityTime = false;
                }
                
                if ($lastActivityTime && $lastActivityTime > 0) {
                    $timeDiff = $currentTime - $lastActivityTime;
                    
                    // User is active if LastActivity is within the threshold (5 minutes)
                    if ($timeDiff < $activeThreshold) {
                        $status = 'active';
                    }
                    
                    // Format LastActive display with relative time for recent activity
                    if ($timeDiff < 60) {
                        // Less than 1 minute ago
                        $lastActive = 'Just now';
                    } elseif ($timeDiff < 3600) {
                        // Less than 1 hour ago - show minutes
                        $minutes = floor($timeDiff / 60);
                        $lastActive = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
                    } elseif ($timeDiff < 86400) {
                        // Less than 1 day ago - show hours
                        $hours = floor($timeDiff / 3600);
                        $lastActive = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                    } elseif ($timeDiff < 604800) {
                        // Less than 1 week ago - show days
                        $days = floor($timeDiff / 86400);
                        $lastActive = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                    } else {
                        // More than 1 week ago - show full date
                        $lastActive = date('M d, Y H:i', $lastActivityTime);
                    }
                } else {
                    $lastActive = 'Never';
                }
            } else {
                $lastActive = 'Never';
            }
        } else {
            $lastActive = 'Never';
        }
        
        // FALLBACK METHOD: Check session files (may not work in all online environments)
        // Only use this if LastActivity check didn't find the user as active
        if ($status === 'inactive' && isset($activeUserSessions[$studentId])) {
            $status = 'active';
            // If we have LastActivity, keep it; otherwise set a default
            if ($lastActive === 'Never') {
                $lastActive = 'Just now';
            }
        }
        
        $users[] = [
            'id' => $autoId++,
            'idNumber' => $studentId,
            'name' => $r['FullName'],
            'email' => $r['Email'],
            'course' => $r['Course'],
            'yearLevel' => $r['YearLevel'],
            'mobile' => '',
            'role' => $role,
            'status' => $status,
            'lastActive' => $lastActive
        ];
    }
    $stmt->close();
    
    // Apply status filter if specified (after determining status)
    if ($statusFilter !== '') {
        $users = array_filter($users, function($user) use ($statusFilter) {
            return $user['status'] === $statusFilter;
        });
        $users = array_values($users); // Re-index array
    }
    
    // Recalculate total after filtering
    $total = count($users);
    
    // Apply pagination after filtering
    $users = array_slice($users, $offset, $limit);
    
    // Recalculate IDs for paginated results
    foreach ($users as $index => &$user) {
        $user['id'] = $offset + $index + 1;
    }
    unset($user);
    
    $totalPages = max(1, (int)ceil($total / $limit));

    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => $total,
        'totalPages' => $totalPages
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management - SeQueueR</title>
    <link rel="icon" type="image/png" href="/Frontend/favicon.php">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Admin Header -->
    <?php include 'Header.php'; ?>
    
    <!-- Main Content -->
    <main class="bg-gray-50 min-h-screen">
        <div class="py-8 px-6 md:px-10 mx-4 md:mx-8 lg:mx-12">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Account Management</h1>
                    <p class="text-gray-600 mt-2">Manage working scholar accounts and permissions</p>
                </div>
                <!-- Action Buttons -->
                <div class="mt-4 sm:mt-0 flex items-center space-x-3">
                    <!-- Export Dropdown -->
                    <div class="relative" id="exportDropdown">
                        <button id="exportBtn" class="border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-semibold py-2 px-4 rounded-lg flex items-center space-x-2" onclick="toggleExportDropdown(event)">
                            <i class="fas fa-download"></i>
                            <span>Export</span>
                            <i class="fas fa-chevron-down ml-1"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div id="exportMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-10">
                            <div class="py-2">
                                <div class="px-4 py-2 text-sm font-medium text-gray-700 border-b border-gray-100">
                                    File Type
                                </div>
                                <div class="py-1">
                                    <button onclick="exportToPDF()" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-3">
                                        <div class="w-6 h-6 bg-red-500 rounded flex items-center justify-center">
                                            <span class="text-white text-xs font-bold">PDF</span>
                                        </div>
                                        <span>PDF</span>
                                    </button>
                                    <button onclick="exportToExcel()" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-3">
                                        <div class="w-6 h-6 bg-green-500 rounded flex items-center justify-center">
                                            <span class="text-white text-xs font-bold">X</span>
                                        </div>
                                        <span>Excel</span>
                                    </button>
                                    <button onclick="exportToCSV()" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-3">
                                        <div class="w-6 h-6 bg-blue-500 rounded flex items-center justify-center">
                                            <span class="text-white text-xs font-bold">CSV</span>
                                        </div>
                                        <span>CSV</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add Account Button -->
                    <button onclick="showAddAccountModal()" class="bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Add Account</span>
                    </button>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:space-x-4 space-y-4 lg:space-y-0">
                    <!-- Search Input -->
                    <div class="flex-1">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" id="searchInput" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Search by id number, name, email address or role">
                        </div>
                    </div>
                    
                    <!-- Filter Dropdowns -->
                    <div class="flex flex-col sm:flex-row sm:space-x-4 space-y-4 sm:space-y-0">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-circle text-gray-400 text-xs"></i>
                            </div>
                            <select id="statusFilter" class="appearance-none bg-white border border-gray-300 rounded-md pl-8 pr-8 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                            </div>
                        </div>
                        
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-id-badge text-gray-400"></i>
                            </div>
                            <select id="roleFilter" class="appearance-none bg-white border border-gray-300 rounded-md pl-9 pr-8 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Roles</option>
                                <option value="Working">Working Scholar</option>
                                <option value="admin">Admin</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                            </div>
                        </div>
                        
                        <button id="clearFiltersBtn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-md flex items-center space-x-2">
                            <i class="fas fa-sync-alt"></i>
                            <span>Clear Filters</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto overflow-y-visible">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('idNumber')">
                                    <div class="flex items-center space-x-1">
                                        <span>ID Number</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('name')">
                                    <div class="flex items-center space-x-1">
                                        <span>Name</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('email')">
                                    <div class="flex items-center space-x-1">
                                        <span>Email Address</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('role')">
                                    <div class="flex items-center space-x-1">
                                        <span>Role</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('status')">
                                    <div class="flex items-center space-x-1">
                                        <span>Status</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('lastActive')">
                                    <div class="flex items-center space-x-1">
                                        <span>Last Active</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <span>Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-6">
                <div class="text-sm text-gray-700 mb-4 sm:mb-0">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalResults">0</span> results
                </div>
                <div id="pagination" class="flex items-center space-x-2">
                    <!-- Pagination will be generated dynamically -->
                </div>
            </div>
        </div>
    </main>
    
    <!-- View User Details Modal -->
    <div id="viewUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-blue-600" id="modalTitle">View User Details</h2>
                <button onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 space-y-6">
                <!-- Personal Information Section -->
                <div>
                    <h3 class="text-lg font-semibold text-blue-600 mb-4 flex items-center">
                        <i class="fas fa-user mr-2"></i>
                        Personal Information
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-blue-600">Full Name<span class="text-red-500">*</span></label>
                            <p class="text-gray-900 font-medium mt-1" id="modalFullName">--</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-blue-600">ID Number<span class="text-red-500">*</span></label>
                            <p class="text-gray-900 font-medium mt-1" id="modalIdNumber">--</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-blue-600">Course/Program<span class="text-red-500">*</span></label>
                            <p class="text-gray-900 font-medium mt-1" id="modalCourse">--</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-blue-600">Year Level<span class="text-red-500">*</span></label>
                            <p class="text-gray-900 font-medium mt-1" id="modalYearLevel">--</p>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information Section -->
                <div>
                    <h3 class="text-lg font-semibold text-blue-600 mb-4 flex items-center">
                        <i class="fas fa-phone mr-2"></i>
                        Contact Information
                    </h3>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="text-sm font-medium text-blue-600">Email Address<span class="text-red-500">*</span></label>
                            <p class="text-gray-900 font-medium mt-1 break-words" id="modalEmail">--</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-blue-600">Mobile Number<span class="text-red-500">*</span></label>
                            <p class="text-gray-900 font-medium mt-1" id="modalMobile">--</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="flex justify-end p-6">
                <button onclick="closeViewModal()" class="border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium" style="padding: 8px 24px; width: 150px; height: 40px;">
                    Close
                </button>
            </div>
        </div>
    </div>
    
    <!-- Add Account Modal -->
    <div id="addAccountModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 sticky top-0 bg-white rounded-t-2xl">
                <h2 class="text-2xl font-bold text-blue-600">Create Working Scholar Account</h2>
                <button onclick="closeAddAccountModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <form id="addAccountForm" class="p-6 space-y-6">
                <!-- Personal Information Section -->
                <div>
                    <h3 class="text-lg font-semibold text-blue-600 mb-4 flex items-center">
                        <i class="fas fa-user mr-2"></i>
                        Personal Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Full Name<span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="addFullName" name="fullName" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter complete name (Last, First, Middle)">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                ID Number<span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="addIdNumber" name="idNumber" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., 2021-12345">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Course/Program<span class="text-red-500">*</span>
                            </label>
                            <select id="addCourse" name="course" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none bg-white"
                                    style="background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 1.25rem;">
                                <option value="">Select course</option>
                                <option value="BSIT">BSIT</option>
                                <option value="BSCS">BSCS</option>
                                <option value="BSBA">BSBA</option>
                                <option value="BSA">BSA</option>
                                <option value="BSEE">BSEE</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Year Level<span class="text-red-500">*</span>
                            </label>
                            <select id="addYearLevel" name="yearLevel" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none bg-white"
                                    style="background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 1.25rem;">
                                <option value="">Select year level</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information Section -->
                <div>
                    <h3 class="text-lg font-semibold text-blue-600 mb-4 flex items-center">
                        <i class="fas fa-phone mr-2"></i>
                        Contact Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Email Address<span class="text-red-500">*</span>
                            </label>
                            <input type="email" id="addEmail" name="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="student@uc.edu.ph or personal email">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Mobile Number<span class="text-red-500">*</span>
                            </label>
                            <input type="tel" id="addMobile" name="mobile" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., +63 912 345 6789">
                        </div>
                    </div>
                </div>
                
                <!-- Account Security Setup Section -->
                <div>
                    <h3 class="text-lg font-semibold text-blue-600 mb-4 flex items-center">
                        <i class="fas fa-shield-alt mr-2"></i>
                        Account Security Setup
                    </h3>
                    <div class="space-y-3">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="radio" name="passwordOption" value="auto" checked
                                   class="w-4 h-4 text-blue-600 focus:ring-blue-500"
                                   onchange="togglePasswordOption()">
                            <span class="text-gray-700">Auto-generate secure password (recommended)</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="radio" name="passwordOption" value="manual"
                                   class="w-4 h-4 text-blue-600 focus:ring-blue-500"
                                   onchange="togglePasswordOption()">
                            <span class="text-gray-700">Set temporary password manually</span>
                        </label>
                    </div>
                    
                    <!-- Auto-generated Password Section -->
                    <div id="autoPasswordSection" class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <label class="block text-sm font-medium text-blue-600 mb-2">
                            Generated Password
                        </label>
                        <div class="flex items-center space-x-2">
                            <input type="text" id="generatedPassword" readonly
                                   class="flex-1 px-4 py-2 bg-yellow-100 border border-yellow-300 rounded-lg font-mono text-gray-900"
                                   value="">
                            <button type="button" onclick="regeneratePassword()"
                                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                                Regenerate
                            </button>
                            <button type="button" onclick="copyPassword()"
                                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                                Copy
                            </button>
                        </div>
                    </div>
                    
                    <!-- Manual Password Section -->
                    <div id="manualPasswordSection" class="hidden mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Temporary Password<span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="manualPassword" name="manualPassword"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter temporary password">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Confirm Password<span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="confirmPassword" name="confirmPassword"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Confirm temporary password">
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Modal Footer -->
            <div class="flex justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeAddAccountModal()" 
                        class="border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium"
                        style="padding: 10px 24px; width: 150px; height: 44px;">
                    Cancel
                </button>
                <button type="button" onclick="submitAddAccount()" 
                        class="bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition font-medium flex items-center justify-center gap-2"
                        style="padding: 10px 24px; width: 180px; height: 44px;">
                    <i class="fas fa-user-plus"></i>
                    Add Account
                </button>
            </div>
        </div>
    </div>
    
    <!-- Success Confirmation Modal -->
    <div id="successModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 p-8">
            <!-- Success Icon -->
            <div class="flex justify-center mb-6">
                <div class="w-24 h-24 bg-yellow-400 rounded-full flex items-center justify-center">
                    <i class="fas fa-check text-white text-4xl"></i>
                </div>
            </div>
            
            <!-- Success Message -->
            <h2 class="text-2xl font-bold text-blue-600 text-center mb-2">
                Working Scholar Account Created!
            </h2>
            <p class="text-gray-600 text-center mb-6">
                Account successfully added to SeQueueR system
            </p>
            
            <!-- Account Details Summary -->
            <div class="bg-blue-50 rounded-lg p-6 mb-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-gray-600">Full Name</label>
                        <p class="font-semibold text-gray-900" id="successFullName">--</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">ID Number</label>
                        <p class="font-semibold text-gray-900" id="successIdNumber">--</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Course/Program</label>
                        <p class="font-semibold text-gray-900" id="successCourse">--</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Year Level</label>
                        <p class="font-semibold text-gray-900" id="successYearLevel">--</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Email</label>
                        <p class="font-semibold text-gray-900 break-words" id="successEmail">--</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Mobile Number</label>
                        <p class="font-semibold text-gray-900" id="successMobile">--</p>
                    </div>
                </div>
            </div>
            
            <!-- Done Button -->
            <div class="flex justify-center">
                <button onclick="closeSuccessModal()" 
                        class="bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition font-semibold"
                        style="padding: 12px 48px; width: 200px; height: 48px;">
                    Done
                </button>
            </div>
        </div>
    </div>
    
    <!-- Edit/Update Account Modal -->
    <div id="editAccountModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 sticky top-0 bg-white rounded-t-2xl">
                <h2 class="text-2xl font-bold text-blue-600" id="editModalTitle">Update Account</h2>
                <button onclick="closeEditAccountModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <form id="editAccountForm" class="p-6 space-y-6">
                <input type="hidden" id="editUserId" name="userId">
                
                <!-- Personal Information Section -->
                <div>
                    <h3 class="text-lg font-semibold text-blue-600 mb-4 flex items-center">
                        <i class="fas fa-user mr-2"></i>
                        Personal Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Full Name<span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="editFullName" name="fullName" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter complete name (Last, First, Middle)">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                ID Number<span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="editIdNumber" name="idNumber" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., 2021-12345">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Course/Program<span class="text-red-500">*</span>
                            </label>
                            <select id="editCourse" name="course" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none bg-white"
                                    style="background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 1.25rem;">
                                <option value="">Select course</option>
                                <option value="BSIT">BSIT</option>
                                <option value="BSCS">BSCS</option>
                                <option value="BSBA">BSBA</option>
                                <option value="BSA">BSA</option>
                                <option value="BSEE">BSEE</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Year Level<span class="text-red-500">*</span>
                            </label>
                            <select id="editYearLevel" name="yearLevel" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none bg-white"
                                    style="background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 1.25rem;">
                                <option value="">Select year level</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information Section -->
                <div>
                    <h3 class="text-lg font-semibold text-blue-600 mb-4 flex items-center">
                        <i class="fas fa-phone mr-2"></i>
                        Contact Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Email Address<span class="text-red-500">*</span>
                            </label>
                            <input type="email" id="editEmail" name="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="student@uc.edu.ph or personal email">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Mobile Number<span class="text-red-500">*</span>
                            </label>
                            <input type="tel" id="editMobile" name="mobile" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., +63 912 345 6789">
                        </div>
                    </div>
                </div>
                
                <!-- Account Security Setup Section -->
                <div>
                    <h3 class="text-lg font-semibold text-blue-600 mb-4 flex items-center">
                        <i class="fas fa-shield-alt mr-2"></i>
                        Account Security Setup
                    </h3>
                    <div class="space-y-3">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="radio" name="editPasswordOption" value="auto" checked
                                   class="w-4 h-4 text-blue-600 focus:ring-blue-500"
                                   onchange="toggleEditPasswordOption()">
                            <span class="text-gray-700">Auto-generate secure password (recommended)</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="radio" name="editPasswordOption" value="manual"
                                   class="w-4 h-4 text-blue-600 focus:ring-blue-500"
                                   onchange="toggleEditPasswordOption()">
                            <span class="text-gray-700">Set temporary password manually</span>
                        </label>
                    </div>
                    
                    <!-- Auto-generated Password Section -->
                    <div id="editAutoPasswordSection" class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <label class="block text-sm font-medium text-blue-600 mb-2">
                            Generated Password
                        </label>
                        <div class="flex items-center space-x-2">
                            <input type="text" id="editGeneratedPassword" readonly
                                   class="flex-1 px-4 py-2 bg-yellow-100 border border-yellow-300 rounded-lg font-mono text-gray-900"
                                   value="">
                            <button type="button" onclick="regenerateEditPassword()"
                                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                                Regenerate
                            </button>
                            <button type="button" onclick="copyEditPassword()"
                                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                                Copy
                            </button>
                        </div>
                    </div>
                    
                    <!-- Manual Password Section -->
                    <div id="editManualPasswordSection" class="hidden mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Temporary Password<span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="editManualPassword" name="manualPassword"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter temporary password">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-blue-600 mb-2">
                                Confirm Password<span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="editConfirmPassword" name="confirmPassword"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Confirm temporary password">
                        </div>
                    </div>
                </div>
            </form>
            
            <!-- Modal Footer -->
            <div class="flex justify-end gap-3 p-6 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="closeEditAccountModal()" 
                        class="border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium"
                        style="padding: 10px 24px; width: 150px; height: 44px;">
                    Cancel
                </button>
                <button type="button" onclick="submitEditAccount()" 
                        class="bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition font-medium flex items-center justify-center gap-2"
                        style="padding: 10px 24px; width: 200px; height: 44px;">
                    <i class="fas fa-user-check"></i>
                    Update Account
                </button>
            </div>
        </div>
    </div>
    
    <!-- Include Footer -->
    <?php include '../../Footer.php'; ?>
    
    <script>
        // Backend-ready JavaScript for Account Management
        let users = [];
        let currentPage = 1;
        let totalPages = 1;
        let totalCount = 0;
        let itemsPerPage = 10;
        let currentSort = { column: '', direction: 'asc' };
        let currentFilters = {
            search: '',
            status: '',
            role: ''
        };
        
        // Initialize the interface
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
            setupEventListeners();
        });
        
        // Load users from backend
        function loadUsers() {
            const params = new URLSearchParams({
                page: currentPage,
                limit: itemsPerPage,
                sort: currentSort.column,
                direction: currentSort.direction,
                ...currentFilters
            });
            
            // Fetch from backend in this file
            console.log('Loading users from:', `User.php?action=list&${params}`);
            fetch(`User.php?action=list&${params}`)
                .then(response => {
                    console.log('Response status:', response.status, response.statusText);
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('Server error:', response.status, text);
                            throw new Error(`Server error: ${response.status} - ${text.substring(0, 200)}`);
                        });
                    }
                    return response.text().then(text => {
                        console.log('Raw response:', text.substring(0, 500));
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse error:', e, 'Text:', text);
                            throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed data:', data);
                    if (data && data.success !== false) {
                        users = data.users || [];
                        totalPages = data.totalPages || 1;
                        totalCount = data.total || users.length;
                        console.log('Users loaded:', users.length, 'Total:', totalCount);
                        updateUsersTable();
                        updatePagination();
                        updateExportButton();
                    } else {
                        console.error('Backend error:', data);
                        alert('Error loading users: ' + (data && data.error ? data.error : 'Unknown error'));
                        users = [];
                        totalPages = 1;
                        totalCount = 0;
                        updateUsersTable();
                        updatePagination();
                        updateExportButton();
                    }
                })
                .catch(error => {
                    console.error('Error loading users:', error);
                    alert('Failed to load users: ' + error.message);
                    // No dummy data - empty state
                    users = [];
                    totalPages = 1;
                    totalCount = 0;
                    updateUsersTable();
                    updatePagination();
                    updateExportButton();
                });
        }
        
        // Update users table
        function updateUsersTable() {
            const tbody = document.getElementById('usersTableBody');
            
            if (users.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-users text-gray-300 text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                                <p class="text-gray-500">No user accounts available at the moment.</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = users.map(user => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-medium text-gray-900">${user.idNumber}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="h-10 w-10 flex-shrink-0">
                                <img class="h-10 w-10 rounded-full object-cover" src="${user.profileImage || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.name) + '&background=1e40af&color=fff'}" alt="${user.name}">
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900">${user.name}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm text-gray-900">${user.email}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full ${
                            user.role === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'
                        }">
                            ${user.role === 'admin' ? 'Admin' : (user.role === 'Working' ? 'Working Scholar' : (user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1).replace(/_/g, ' ') : 'Working Scholar'))}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-2 h-2 rounded-full mr-2 ${
                                user.status === 'active' ? 'bg-green-500' : 'bg-gray-400'
                            }"></div>
                            <span class="text-sm text-gray-900 capitalize">${user.status === 'active' ? 'Active' : 'Inactive'}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div class="flex items-center">
                            <i class="fas fa-clock text-gray-400 mr-2"></i>
                            <span>${user.lastActive || 'Never'}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 relative">
                        <button onclick="toggleActionMenu(${user.id}, event)" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div id="actionDropdown-${user.id}" class="hidden fixed bg-white rounded-xl shadow-xl border border-gray-200" style="z-index: 9999; min-width: 140px;">
                            <div class="py-2">
                                <button onclick="viewUser(${user.id})" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 flex items-center space-x-3 transition">
                                    <i class="far fa-eye text-gray-600 text-base"></i>
                                    <span class="font-medium">View</span>
                                </button>
                                <button onclick="editUser(${user.id})" class="w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 flex items-center space-x-3 transition">
                                    <i class="fas fa-edit text-blue-500 text-base"></i>
                                    <span class="font-medium">Edit</span>
                                </button>
                                <button onclick="deleteUser(${user.id})" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center space-x-3 transition">
                                    <i class="fas fa-trash-alt text-red-600 text-base"></i>
                                    <span class="font-medium">Delete</span>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            `).join('');
        }
        
        // Update pagination
        function updatePagination() {
            const pagination = document.getElementById('pagination');
            const showingFrom = document.getElementById('showingFrom');
            const showingTo = document.getElementById('showingTo');
            const totalResults = document.getElementById('totalResults');
            
            const startItem = (currentPage - 1) * itemsPerPage + 1;
            const endItem = Math.min(currentPage * itemsPerPage, totalCount);
            
            showingFrom.textContent = totalCount > 0 ? startItem : 0;
            showingTo.textContent = endItem;
            totalResults.textContent = totalCount;
            
            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }
            
            let paginationHTML = '';
            
            // Previous button
            if (currentPage > 1) {
                paginationHTML += `
                    <button onclick="changePage(${currentPage - 1})" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        < Previous
                    </button>
                `;
            } else {
                paginationHTML += `
                    <button disabled class="px-3 py-2 text-sm font-medium text-gray-400 bg-gray-300 border border-gray-300 rounded-md cursor-not-allowed">
                        < Previous
                    </button>
                `;
            }
            
            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
            
            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }
            
            if (startPage > 1) {
                paginationHTML += `
                    <button onclick="changePage(1)" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">1</button>
                `;
                if (startPage > 2) {
                    paginationHTML += `<span class="px-3 py-2 text-sm text-gray-500">...</span>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                paginationHTML += `
                    <button onclick="changePage(${i})" class="px-3 py-2 text-sm font-medium ${
                        i === currentPage ? 'text-white bg-blue-900 border-blue-900' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50'
                    } border rounded-md">
                        ${i}
                    </button>
                `;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHTML += `<span class="px-3 py-2 text-sm text-gray-500">...</span>`;
                }
                paginationHTML += `
                    <button onclick="changePage(${totalPages})" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">${totalPages}</button>
                `;
            }
            
            // Next button
            if (currentPage < totalPages) {
                paginationHTML += `
                    <button onclick="changePage(${currentPage + 1})" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Next >
                    </button>
                `;
            } else {
                paginationHTML += `
                    <button disabled class="px-3 py-2 text-sm font-medium text-gray-400 bg-gray-300 border border-gray-300 rounded-md cursor-not-allowed">
                        Next >
                    </button>
                `;
            }
            
            pagination.innerHTML = paginationHTML;
        }
        
        // Update export button state
        function updateExportButton() {
            const exportBtn = document.getElementById('exportBtn');
            if (users.length === 0) {
                exportBtn.classList.add('opacity-50', 'cursor-not-allowed');
                exportBtn.classList.remove('hover:bg-gray-50');
            } else {
                exportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                exportBtn.classList.add('hover:bg-gray-50');
            }
        }
        
        // Setup event listeners
        function setupEventListeners() {
            document.getElementById('searchInput').addEventListener('input', debounce(handleSearch, 300));
            document.getElementById('statusFilter').addEventListener('change', handleFilterChange);
            document.getElementById('roleFilter').addEventListener('change', handleFilterChange);
            document.getElementById('clearFiltersBtn').addEventListener('click', clearFilters);
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(event) {
                const exportDropdown = document.getElementById('exportDropdown');
                if (!exportDropdown.contains(event.target)) {
                    closeExportDropdown();
                }
                
                // Close all action menus if clicking outside
                document.querySelectorAll('[id^="actionDropdown-"]').forEach(menu => {
                    if (!menu.contains(event.target) && !event.target.closest('button[onclick*="toggleActionMenu"]')) {
                        menu.classList.add('hidden');
                    }
                });
                
                // Close view modal when clicking outside
                const viewModal = document.getElementById('viewUserModal');
                if (event.target === viewModal) {
                    closeViewModal();
                }
            });
            
            // Close modals on Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const viewModal = document.getElementById('viewUserModal');
                    if (!viewModal.classList.contains('hidden')) {
                        closeViewModal();
                    }
                    
                    const addAccountModal = document.getElementById('addAccountModal');
                    if (!addAccountModal.classList.contains('hidden')) {
                        closeAddAccountModal();
                    }
                    
                    const editAccountModal = document.getElementById('editAccountModal');
                    if (!editAccountModal.classList.contains('hidden')) {
                        closeEditAccountModal();
                    }
                    
                    const successModal = document.getElementById('successModal');
                    if (!successModal.classList.contains('hidden')) {
                        closeSuccessModal();
                    }
                }
            });
            
            // Close add account modal when clicking outside
            document.getElementById('addAccountModal')?.addEventListener('click', function(event) {
                if (event.target === this) {
                    closeAddAccountModal();
                }
            });
            
            // Close edit account modal when clicking outside
            document.getElementById('editAccountModal')?.addEventListener('click', function(event) {
                if (event.target === this) {
                    closeEditAccountModal();
                }
            });
            
            // Close success modal when clicking outside
            document.getElementById('successModal')?.addEventListener('click', function(event) {
                if (event.target === this) {
                    closeSuccessModal();
                }
            });
        }
        
        // Handle search
        function handleSearch(event) {
            currentFilters.search = event.target.value;
            currentPage = 1;
            loadUsers();
        }
        
        // Handle filter changes
        function handleFilterChange(event) {
            const filterType = event.target.id.replace('Filter', '');
            currentFilters[filterType] = event.target.value;
            currentPage = 1;
            loadUsers();
        }
        
        // Clear all filters
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('roleFilter').value = '';
            
            currentFilters = {
                search: '',
                status: '',
                role: ''
            };
            
            currentPage = 1;
            loadUsers();
        }
        
        // Change page
        function changePage(page) {
            currentPage = page;
            loadUsers();
        }
        
        // Sort table
        function sortTable(column) {
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.direction = 'asc';
            }
            
            currentPage = 1;
            loadUsers();
        }
        
        // Toggle export dropdown
        function toggleExportDropdown(event) {
            event.preventDefault();
            event.stopPropagation();
            
            if (users.length === 0) {
                console.log('No users to export');
                return;
            }
            
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
        
        // Toggle action menu
        function toggleActionMenu(userId, event) {
            event.stopPropagation();
            const menu = document.getElementById(`actionDropdown-${userId}`);
            const button = event.currentTarget;
            
            // Close all other action menus
            document.querySelectorAll('[id^="actionDropdown-"]').forEach(m => {
                if (m.id !== `actionDropdown-${userId}`) {
                    m.classList.add('hidden');
                }
            });
            
            if (menu.classList.contains('hidden')) {
                // Get button position
                const rect = button.getBoundingClientRect();
                
                // Position the menu: below the button and aligned to the right
                menu.style.top = `${rect.bottom + 8}px`;
                menu.style.left = `${rect.left - 100}px`; // Position to the left of the button
                
                menu.classList.remove('hidden');
            } else {
                menu.classList.add('hidden');
            }
        }
        
        // Export functions
        function exportToPDF() {
            if (users.length === 0) return;
            closeExportDropdown();
            console.log('Export to PDF - Backend not implemented yet');
            // TODO: Implement PDF export
        }
        
        function exportToExcel() {
            if (users.length === 0) return;
            closeExportDropdown();
            console.log('Export to Excel - Backend not implemented yet');
            // TODO: Implement Excel export
        }
        
        function exportToCSV() {
            if (users.length === 0) return;
            closeExportDropdown();
            console.log('Export to CSV - Backend not implemented yet');
            // TODO: Implement CSV export
        }
        
        // Action functions
        function viewUser(userId) {
            // Find the user by ID
            const user = users.find(u => u.id === userId);
            if (!user) {
                console.error('User not found:', userId);
                return;
            }
            
            // Populate modal with user data
            document.getElementById('modalTitle').textContent = `View #${user.idNumber || 'Unknown'} Details`;
            document.getElementById('modalFullName').textContent = user.name || '--';
            document.getElementById('modalIdNumber').textContent = user.idNumber || '--';
            document.getElementById('modalCourse').textContent = user.course || '--';
            document.getElementById('modalYearLevel').textContent = user.yearLevel || '--';
            document.getElementById('modalEmail').textContent = user.email || '--';
            document.getElementById('modalMobile').textContent = user.mobile || '--';
            
            // Show modal
            document.getElementById('viewUserModal').classList.remove('hidden');
            
            // Close action menu
            document.getElementById(`actionDropdown-${userId}`).classList.add('hidden');
        }
        
        function closeViewModal() {
            document.getElementById('viewUserModal').classList.add('hidden');
        }
        
        // Make function globally available
        window.closeViewModal = closeViewModal;
        
        function editUser(userId) {
            // Find the user by ID
            const user = users.find(u => u.id === userId);
            if (!user) {
                console.error('User not found:', userId);
                return;
            }
            
            // Populate modal with user data
            document.getElementById('editModalTitle').textContent = `Update #${user.idNumber || 'Unknown'} Account`;
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editFullName').value = user.name || '';
            document.getElementById('editIdNumber').value = user.idNumber || '';
            document.getElementById('editCourse').value = user.course || '';
            document.getElementById('editYearLevel').value = user.yearLevel || '';
            document.getElementById('editEmail').value = user.email || '';
            document.getElementById('editMobile').value = user.mobile || '';
            
            // Generate initial password
            regenerateEditPassword();
            
            // Show auto password section by default
            document.getElementById('editAutoPasswordSection').classList.remove('hidden');
            document.getElementById('editManualPasswordSection').classList.add('hidden');
            
            // Reset radio buttons to auto
            document.querySelector('input[name="editPasswordOption"][value="auto"]').checked = true;
            
            // Show modal
            document.getElementById('editAccountModal').classList.remove('hidden');
            
            // Close action menu
            document.getElementById(`actionDropdown-${userId}`).classList.add('hidden');
        }
        
        function closeEditAccountModal() {
            document.getElementById('editAccountModal').classList.add('hidden');
        }
        
        function toggleEditPasswordOption() {
            const autoSelected = document.querySelector('input[name="editPasswordOption"]:checked').value === 'auto';
            const autoSection = document.getElementById('editAutoPasswordSection');
            const manualSection = document.getElementById('editManualPasswordSection');
            
            if (autoSelected) {
                autoSection.classList.remove('hidden');
                manualSection.classList.add('hidden');
            } else {
                autoSection.classList.add('hidden');
                manualSection.classList.remove('hidden');
            }
        }
        
        function regenerateEditPassword() {
            const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            const specialChars = '!@#$%^&*';
            const numbers = '0123456789';
            const upperCase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            const lowerCase = 'abcdefghijklmnopqrstuvwxyz';
            
            let password = '';
            
            // Ensure at least one of each type
            password += upperCase[Math.floor(Math.random() * upperCase.length)];
            password += lowerCase[Math.floor(Math.random() * lowerCase.length)];
            password += numbers[Math.floor(Math.random() * numbers.length)];
            password += specialChars[Math.floor(Math.random() * specialChars.length)];
            
            // Fill the rest randomly (total 12 characters)
            for (let i = password.length; i < 12; i++) {
                password += charset[Math.floor(Math.random() * charset.length)];
            }
            
            // Shuffle the password
            password = password.split('').sort(() => Math.random() - 0.5).join('');
            
            document.getElementById('editGeneratedPassword').value = password;
        }
        
        function copyEditPassword() {
            const passwordInput = document.getElementById('editGeneratedPassword');
            passwordInput.select();
            passwordInput.setSelectionRange(0, 99999); // For mobile devices
            
            navigator.clipboard.writeText(passwordInput.value).then(() => {
                // Show success message
                const copyBtn = event.target.closest('button');
                const originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                copyBtn.classList.add('bg-green-50', 'text-green-600', 'border-green-300');
                
                setTimeout(() => {
                    copyBtn.innerHTML = originalText;
                    copyBtn.classList.remove('bg-green-50', 'text-green-600', 'border-green-300');
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy password:', err);
                alert('Failed to copy password. Please copy manually.');
            });
        }
        
        function submitEditAccount() {
            const form = document.getElementById('editAccountForm');
            
            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Get form data
            const formData = {
                userId: document.getElementById('editUserId').value,
                fullName: document.getElementById('editFullName').value,
                idNumber: document.getElementById('editIdNumber').value,
                course: document.getElementById('editCourse').value,
                yearLevel: document.getElementById('editYearLevel').value,
                email: document.getElementById('editEmail').value,
                mobile: document.getElementById('editMobile').value,
                passwordOption: document.querySelector('input[name="editPasswordOption"]:checked').value,
                password: document.querySelector('input[name="editPasswordOption"]:checked').value === 'auto' 
                    ? document.getElementById('editGeneratedPassword').value 
                    : document.getElementById('editManualPassword').value
            };
            
            // Validate manual password if selected
            if (formData.passwordOption === 'manual') {
                const manualPassword = document.getElementById('editManualPassword').value;
                const confirmPassword = document.getElementById('editConfirmPassword').value;
                
                if (!manualPassword || !confirmPassword) {
                    alert('Please enter and confirm the password.');
                    return;
                }
                
                if (manualPassword !== confirmPassword) {
                    alert('Passwords do not match. Please try again.');
                    return;
                }
            }
            
            // TODO: Send data to backend
            console.log('Updating account data:', formData);
            
            // Simulate API call
            fetch(`/api/admin/users/${formData.userId}/update`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Success:', data);
                alert('Account updated successfully!');
                closeEditAccountModal();
                loadUsers(); // Refresh the user list
            })
            .catch((error) => {
                console.error('Error:', error);
                // For demo purposes, update the user in the array
                const userIndex = users.findIndex(u => u.id == formData.userId);
                if (userIndex !== -1) {
                    users[userIndex] = {
                        ...users[userIndex],
                        name: formData.fullName,
                        idNumber: formData.idNumber,
                        course: formData.course,
                        yearLevel: formData.yearLevel,
                        email: formData.email,
                        mobile: formData.mobile
                    };
                    
                    updateUsersTable();
                    alert('Account updated successfully! (Demo mode - backend not connected)');
                    closeEditAccountModal();
                }
            });
        }
        
        function deleteUser(userId) {
            console.log('Delete user:', userId);
            // TODO: Implement delete confirmation modal
        }
        
        function showAddAccountModal() {
            // Reset form
            document.getElementById('addAccountForm').reset();
            
            // Generate initial password
            regeneratePassword();
            
            // Show auto password section by default
            document.getElementById('autoPasswordSection').classList.remove('hidden');
            document.getElementById('manualPasswordSection').classList.add('hidden');
            
            // Show modal
            document.getElementById('addAccountModal').classList.remove('hidden');
        }
        
        function closeAddAccountModal() {
            document.getElementById('addAccountModal').classList.add('hidden');
        }
        
        function togglePasswordOption() {
            const autoSelected = document.querySelector('input[name="passwordOption"]:checked').value === 'auto';
            const autoSection = document.getElementById('autoPasswordSection');
            const manualSection = document.getElementById('manualPasswordSection');
            
            if (autoSelected) {
                autoSection.classList.remove('hidden');
                manualSection.classList.add('hidden');
            } else {
                autoSection.classList.add('hidden');
                manualSection.classList.remove('hidden');
            }
        }
        
        function regeneratePassword() {
            const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            const specialChars = '!@#$%^&*';
            const numbers = '0123456789';
            const upperCase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            const lowerCase = 'abcdefghijklmnopqrstuvwxyz';
            
            let password = '';
            
            // Ensure at least one of each type
            password += upperCase[Math.floor(Math.random() * upperCase.length)];
            password += lowerCase[Math.floor(Math.random() * lowerCase.length)];
            password += numbers[Math.floor(Math.random() * numbers.length)];
            password += specialChars[Math.floor(Math.random() * specialChars.length)];
            
            // Fill the rest randomly (total 12 characters)
            for (let i = password.length; i < 12; i++) {
                password += charset[Math.floor(Math.random() * charset.length)];
            }
            
            // Shuffle the password
            password = password.split('').sort(() => Math.random() - 0.5).join('');
            
            document.getElementById('generatedPassword').value = password;
        }
        
        function copyPassword() {
            const passwordInput = document.getElementById('generatedPassword');
            passwordInput.select();
            passwordInput.setSelectionRange(0, 99999); // For mobile devices
            
            navigator.clipboard.writeText(passwordInput.value).then(() => {
                // Show success message
                const copyBtn = event.target.closest('button');
                const originalText = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                copyBtn.classList.add('bg-green-50', 'text-green-600', 'border-green-300');
                
                setTimeout(() => {
                    copyBtn.innerHTML = originalText;
                    copyBtn.classList.remove('bg-green-50', 'text-green-600', 'border-green-300');
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy password:', err);
                alert('Failed to copy password. Please copy manually.');
            });
        }
        
        function submitAddAccount() {
            const form = document.getElementById('addAccountForm');
            
            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Get form data
            const formData = {
                fullName: document.getElementById('addFullName').value,
                idNumber: document.getElementById('addIdNumber').value,
                course: document.getElementById('addCourse').value,
                yearLevel: document.getElementById('addYearLevel').value,
                email: document.getElementById('addEmail').value,
                mobile: document.getElementById('addMobile').value,
                passwordOption: document.querySelector('input[name="passwordOption"]:checked').value,
                password: document.querySelector('input[name="passwordOption"]:checked').value === 'auto' 
                    ? document.getElementById('generatedPassword').value 
                    : document.getElementById('manualPassword').value
            };
            
            // Validate manual password if selected
            if (formData.passwordOption === 'manual') {
                const manualPassword = document.getElementById('manualPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                
                if (!manualPassword || !confirmPassword) {
                    alert('Please enter and confirm the password.');
                    return;
                }
                
                if (manualPassword !== confirmPassword) {
                    alert('Passwords do not match. Please try again.');
                    return;
                }
            }
            
            // TODO: Send data to backend
            console.log('Submitting account data:', formData);
            
            // Create account via backend handler in this file
            fetch('User.php?action=create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.success) {
                    closeAddAccountModal();
                    showSuccessModal(formData);
                    loadUsers();
                } else {
                    throw new Error(data && data.error ? data.error : 'Unknown error');
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                alert('Failed to create account: ' + (error?.message || 'Unknown error'));
            });
        }
        
        // Show success modal
        function showSuccessModal(accountData) {
            // Populate modal with account data
            document.getElementById('successFullName').textContent = accountData.fullName;
            document.getElementById('successIdNumber').textContent = accountData.idNumber;
            document.getElementById('successCourse').textContent = accountData.course;
            document.getElementById('successYearLevel').textContent = accountData.yearLevel;
            document.getElementById('successEmail').textContent = accountData.email;
            document.getElementById('successMobile').textContent = accountData.mobile;
            
            // Show modal
            document.getElementById('successModal').classList.remove('hidden');
        }
        
        // Close success modal
        function closeSuccessModal() {
            document.getElementById('successModal').classList.add('hidden');
        }
        
        // Make functions globally available
        window.showAddAccountModal = showAddAccountModal;
        window.closeAddAccountModal = closeAddAccountModal;
        window.togglePasswordOption = togglePasswordOption;
        window.regeneratePassword = regeneratePassword;
        window.copyPassword = copyPassword;
        window.submitAddAccount = submitAddAccount;
        window.showSuccessModal = showSuccessModal;
        window.closeSuccessModal = closeSuccessModal;
        window.closeEditAccountModal = closeEditAccountModal;
        window.toggleEditPasswordOption = toggleEditPasswordOption;
        window.regenerateEditPassword = regenerateEditPassword;
        window.copyEditPassword = copyEditPassword;
        window.submitEditAccount = submitEditAccount;
        
        // Debounce function for search
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Auto-refresh data every 60 seconds
        setInterval(loadUsers, 60000);
        
        // Note: Keep-alive functionality is handled by Header.php for all admin pages
    </script>
</body>
</html>
