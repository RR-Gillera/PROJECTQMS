<?php
session_start();
require_once __DIR__ . '/../../Student/db_config.php';
require_once __DIR__ . '/../admin_functions.php';

// Get export format
$format = $_GET['format'] ?? 'csv';

// Get filters from URL
$search = $_GET['search'] ?? '';
$dateRange = $_GET['date_range'] ?? '';
$status = $_GET['status'] ?? '';

// Get database connection
$conn = getDBConnection();

// Build query (same as History.php)
$where = ["DATE(q.created_at) <= CURDATE()"];
$params = [];
$types = '';

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

// Get all transactions (no limit for export)
$query = "
    SELECT 
        q.*,
        GROUP_CONCAT(qs.service_name SEPARATOR ', ') as services
    FROM queues q
    LEFT JOIN queue_services qs ON q.id = qs.queue_id
    WHERE $whereClause
    GROUP BY q.id
    ORDER BY q.created_at DESC
";

$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
$conn->close();

// Export based on format
switch ($format) {
    case 'pdf':
        exportToPDF($transactions);
        break;
    case 'excel':
        exportToExcel($transactions);
        break;
    case 'csv':
    default:
        exportToCSV($transactions);
        break;
}

// Export to CSV
function exportToCSV($transactions) {
    $filename = "queue_history_" . date('Y-m-d_His') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'Queue Number',
        'Student Name',
        'Student ID',
        'Course/Program',
        'Year Level',
        'Queue Type',
        'Services Requested',
        'Status',
        'Date Created',
        'Time Created',
        'Time Served',
        'Time Completed',
        'Wait Time (minutes)',
        'Service Duration (minutes)'
    ]);
    
    // Data rows
    foreach ($transactions as $transaction) {
        $createdDate = new DateTime($transaction['created_at']);
        $servedTime = $transaction['served_at'] ? (new DateTime($transaction['served_at']))->format('g:i A') : '--';
        $completedTime = $transaction['completed_at'] ? (new DateTime($transaction['completed_at']))->format('g:i A') : '--';
        
        // Calculate wait time
        $waitTime = '--';
        if ($transaction['served_at'] && $transaction['created_at']) {
            $created = new DateTime($transaction['created_at']);
            $served = new DateTime($transaction['served_at']);
            $waitTime = $served->getTimestamp() - $created->getTimestamp();
            $waitTime = round($waitTime / 60); // Convert to minutes
        }
        
        // Calculate service duration
        $serviceDuration = '--';
        if ($transaction['completed_at'] && $transaction['served_at']) {
            $served = new DateTime($transaction['served_at']);
            $completed = new DateTime($transaction['completed_at']);
            $serviceDuration = $completed->getTimestamp() - $served->getTimestamp();
            $serviceDuration = round($serviceDuration / 60); // Convert to minutes
        }
        
        fputcsv($output, [
            $transaction['queue_number'],
            $transaction['student_name'],
            $transaction['student_id'],
            $transaction['course_program'],
            $transaction['year_level'],
            ucfirst($transaction['queue_type']),
            $transaction['services'] ?: 'N/A',
            ucfirst($transaction['status']),
            $createdDate->format('M d, Y'),
            $createdDate->format('g:i A'),
            $servedTime,
            $completedTime,
            $waitTime,
            $serviceDuration
        ]);
    }
    
    fclose($output);
    exit();
}

// Export to Excel (using HTML table method - compatible with Excel)
function exportToExcel($transactions) {
    $filename = "queue_history_" . date('Y-m-d_His') . ".xls";
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    echo '<x:Name>Queue History</x:Name>';
    echo '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
    echo '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '</head>';
    echo '<body>';
    
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr style="background-color: #1e40af; color: white; font-weight: bold;">';
    echo '<th>Queue Number</th>';
    echo '<th>Student Name</th>';
    echo '<th>Student ID</th>';
    echo '<th>Course/Program</th>';
    echo '<th>Year Level</th>';
    echo '<th>Queue Type</th>';
    echo '<th>Services Requested</th>';
    echo '<th>Status</th>';
    echo '<th>Date Created</th>';
    echo '<th>Time Created</th>';
    echo '<th>Time Served</th>';
    echo '<th>Time Completed</th>';
    echo '<th>Wait Time (minutes)</th>';
    echo '<th>Service Duration (minutes)</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($transactions as $transaction) {
        $createdDate = new DateTime($transaction['created_at']);
        $servedTime = $transaction['served_at'] ? (new DateTime($transaction['served_at']))->format('g:i A') : '--';
        $completedTime = $transaction['completed_at'] ? (new DateTime($transaction['completed_at']))->format('g:i A') : '--';
        
        // Calculate wait time
        $waitTime = '--';
        if ($transaction['served_at'] && $transaction['created_at']) {
            $created = new DateTime($transaction['created_at']);
            $served = new DateTime($transaction['served_at']);
            $waitTime = $served->getTimestamp() - $created->getTimestamp();
            $waitTime = round($waitTime / 60);
        }
        
        // Calculate service duration
        $serviceDuration = '--';
        if ($transaction['completed_at'] && $transaction['served_at']) {
            $served = new DateTime($transaction['served_at']);
            $completed = new DateTime($transaction['completed_at']);
            $serviceDuration = $completed->getTimestamp() - $served->getTimestamp();
            $serviceDuration = round($serviceDuration / 60);
        }
        
        // Status color coding
        $statusColors = [
            'completed' => '#d1fae5',
            'stalled' => '#fef3c7',
            'skipped' => '#fee2e2',
            'waiting' => '#dbeafe',
            'serving' => '#e9d5ff'
        ];
        $bgColor = $statusColors[$transaction['status']] ?? '#f3f4f6';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($transaction['queue_number']) . '</td>';
        echo '<td>' . htmlspecialchars($transaction['student_name']) . '</td>';
        echo '<td>' . htmlspecialchars($transaction['student_id']) . '</td>';
        echo '<td>' . htmlspecialchars($transaction['course_program']) . '</td>';
        echo '<td>' . htmlspecialchars($transaction['year_level']) . '</td>';
        echo '<td>' . ucfirst($transaction['queue_type']) . '</td>';
        echo '<td>' . htmlspecialchars($transaction['services'] ?: 'N/A') . '</td>';
        echo '<td style="background-color: ' . $bgColor . '">' . ucfirst($transaction['status']) . '</td>';
        echo '<td>' . $createdDate->format('M d, Y') . '</td>';
        echo '<td>' . $createdDate->format('g:i A') . '</td>';
        echo '<td>' . $servedTime . '</td>';
        echo '<td>' . $completedTime . '</td>';
        echo '<td>' . $waitTime . '</td>';
        echo '<td>' . $serviceDuration . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}

// Export to PDF (HTML print version)
function exportToPDF($transactions) {
    $filename = "queue_history_" . date('Y-m-d_His') . ".html";
    
    // Generate printable HTML that can be printed to PDF
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Queue History Report</title>
        <style>
            @media print {
                .no-print { display: none; }
                @page { margin: 1cm; }
            }
            body { 
                font-family: Arial, sans-serif; 
                font-size: 11px;
                margin: 20px;
                background: white;
            }
            .header { 
                text-align: center; 
                margin-bottom: 30px;
                border-bottom: 3px solid #1e40af;
                padding-bottom: 20px;
            }
            .header h1 { 
                color: #1e40af; 
                margin: 0 0 10px 0;
                font-size: 24px;
            }
            .header .subtitle {
                color: #666;
                font-size: 12px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            th { 
                background-color: #1e40af; 
                color: white; 
                font-weight: bold;
                padding: 12px 8px;
                text-align: left;
                font-size: 10px;
                border: 1px solid #1e40af;
            }
            td { 
                border: 1px solid #ddd; 
                padding: 10px 8px; 
                text-align: left;
                font-size: 10px;
            }
            tr:nth-child(even) { 
                background-color: #f8f9fa; 
            }
            tr:hover {
                background-color: #e9ecef;
            }
            .status-completed { 
                background-color: #d1fae5; 
                padding: 4px 8px;
                border-radius: 4px;
                display: inline-block;
            }
            .status-stalled { 
                background-color: #fef3c7; 
                padding: 4px 8px;
                border-radius: 4px;
                display: inline-block;
            }
            .status-skipped { 
                background-color: #fee2e2; 
                padding: 4px 8px;
                border-radius: 4px;
                display: inline-block;
            }
            .status-cancelled { 
                background-color: #f3f4f6; 
                padding: 4px 8px;
                border-radius: 4px;
                display: inline-block;
            }
            .priority-badge {
                color: #f59e0b;
                font-weight: bold;
            }
            .print-btn {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 24px;
                background-color: #1e40af;
                color: white;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 1000;
            }
            .print-btn:hover {
                background-color: #1e3a8a;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                color: #666;
                font-size: 10px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
        </style>
    </head>
    <body>
        <button class="print-btn no-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print to PDF
        </button>
        
        <div class="header">
            <h1>Queue Management System</h1>
            <h2 style="color: #1e40af; margin: 10px 0;">Transaction History Report</h2>
            <div class="subtitle">
                Generated on: <?php echo date('F d, Y g:i A'); ?><br>
                Total Records: <?php echo count($transactions); ?>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Queue #</th>
                    <th style="width: 20%;">Student Name</th>
                    <th style="width: 12%;">Student ID</th>
                    <th style="width: 18%;">Services</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 15%;">Date & Time</th>
                    <th style="width: 10%;">Wait Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction): ?>
                <?php
                $createdDate = new DateTime($transaction['created_at']);
                
                // Calculate wait time
                $waitTime = '--';
                if ($transaction['served_at'] && $transaction['created_at']) {
                    $created = new DateTime($transaction['created_at']);
                    $served = new DateTime($transaction['served_at']);
                    $diff = $created->diff($served);
                    $waitTime = $diff->h . 'h ' . $diff->i . 'm';
                }
                
                $statusClass = 'status-' . $transaction['status'];
                ?>
                <tr>
                    <td>
                        <?php if ($transaction['queue_type'] === 'priority'): ?>
                        <span class="priority-badge">★</span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($transaction['queue_number']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($transaction['student_name']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['student_id']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['services'] ?: 'N/A'); ?></td>
                    <td><span class="<?php echo $statusClass; ?>"><?php echo ucfirst($transaction['status']); ?></span></td>
                    <td><?php echo $createdDate->format('M d, Y g:i A'); ?></td>
                    <td><?php echo $waitTime; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>This is an official report from the Queue Management System</p>
            <p>© <?php echo date('Y'); ?> SeQueueR - All Rights Reserved</p>
        </div>
        
        <script>
            // Auto-print dialog after 1 second
            setTimeout(function() {
                // Show print dialog
                // window.print();
            }, 1000);
        </script>
    </body>
    </html>
    <?php
    exit();
}
?>