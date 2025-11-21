<?php
/**
 * Backend API for Working Transaction History
 * Returns transaction history filtered by worker's counter for working accounts
 * Returns all transaction history for admin accounts
 */

session_start();
require_once __DIR__ . '/../../Student/db_config.php';
require_once __DIR__ . '/../admin_functions.php';

header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in and is either working or admin
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userRole = strtolower($_SESSION['user']['role'] ?? '');
if ($userRole !== 'working' && $userRole !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$studentId = $_SESSION['user']['studentId'];
$counterNumber = $_SESSION['user']['counterNumber'] ?? null;
$isAdmin = ($userRole === 'admin');

// For working accounts, require counter assignment
if (!$isAdmin && !$counterNumber) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No counter assignment found']);
    exit;
}

$conn = getDBConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$dateRange = $_GET['date_range'] ?? '';
$status = $_GET['status'] ?? '';
$service = $_GET['service'] ?? '';

// Build query - filter by counter_id only for working accounts
$where = ["DATE(q.created_at) <= CURDATE()"];
$params = [];
$types = '';

// Only filter by counter_id for working accounts (not admin)
if (!$isAdmin && $counterNumber) {
    $where[] = "q.counter_id = ?";
    $params[] = $counterNumber;
    $types = 'i';
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
$countQuery = "
    SELECT COUNT(DISTINCT q.id) as total 
    FROM queues q
    WHERE $whereClause
";

$countStmt = $conn->prepare($countQuery);
if ($types) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);
$countStmt->close();

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
$transactions = [];

while ($row = $result->fetch_assoc()) {
    // Format transaction data
    $createdAt = new DateTime($row['created_at']);
    
    $waitTime = '--';
    if ($row['served_at'] && $row['created_at']) {
        $created = new DateTime($row['created_at']);
        $served = new DateTime($row['served_at']);
        $diff = $created->diff($served);
        $waitTime = $diff->h . 'h ' . $diff->i . 'm';
    }
    
    $services = $row['services'] ? explode(', ', $row['services']) : [];
    
    $transactions[] = [
        'id' => $row['id'],
        'queueNumber' => $row['queue_number'],
        'studentName' => $row['student_name'],
        'studentId' => $row['student_id'],
        'serviceType' => !empty($services) ? $services[0] : 'N/A',
        'additionalServices' => count($services) - 1,
        'status' => $row['status'],
        'dateTime' => $createdAt->format('M d, Y'),
        'time' => $createdAt->format('g:i A'),
        'waitTime' => $waitTime,
        'priority' => $row['queue_type'] === 'priority' ? 'priority' : 'regular'
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'transactions' => $transactions,
    'totalPages' => $totalPages,
    'currentPage' => $page,
    'totalResults' => $totalRows
]);
?>

