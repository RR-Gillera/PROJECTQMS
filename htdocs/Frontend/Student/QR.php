<?php
session_start();
require_once 'db_config.php';

// Get data from session
$studentData = [
    'full_name' => $_SESSION['fullname'] ?? '',
    'student_id' => $_SESSION['studentid'] ?? '',
    'year_level' => $_SESSION['yearlevel'] ?? '',
    'course_program' => $_SESSION['courseprogram'] ?? ''
];

$selectedServices = $_SESSION['selected_services'] ?? [];
$priorityGroup = $_SESSION['priority_group'] ?? 'no';
$generateQr = $_SESSION['generate_qr'] ?? 'no';

// Validate required data
if (empty($studentData['full_name']) || empty($studentData['student_id'])) {
    header("Location: index.php");
    exit();
}

try {
    // Get database connection
    $conn = getDBConnection();
    
    // Determine queue type
    $queueType = 'regular';
    if (isset($_SESSION['priority_group']) && $_SESSION['priority_group'] === 'yes') {
        $queueType = 'priority';
    } elseif ($priorityGroup === 'yes') {
        $queueType = 'priority';
    }
    
    // Generate queue number
    $queueNumber = generateQueueNumber($conn, $queueType);
    
    // Generate QR code URL that redirects to track.php
    $trackingUrl = "https://qmscharlie.byethost5.com/Frontend/Student/track.php?queue=" . urlencode($queueNumber);
    $qrCodeImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($trackingUrl);
    
    // Prepare queue data
    $queueData = [
        'queue_number' => $queueNumber,
        'queue_type' => $queueType,
        'student_name' => $studentData['full_name'],
        'student_id' => $studentData['student_id'],
        'year_level' => $studentData['year_level'],
        'course_program' => $studentData['course_program'],
        'services_count' => count($selectedServices),
        'has_qr' => 1,
        'qr_code_url' => $qrCodeImageUrl
    ];
    
    // Insert queue record
    $queueId = insertQueueRecord($conn, $queueData);
    
    // Insert selected services
    if (!empty($selectedServices)) {
        insertQueueServices($conn, $queueId, $selectedServices);
    }
    
    // Log queue generation
    logQueueAction($conn, $queueId, 'generated', 'Queue number generated with QR code - Type: ' . $queueType);
    
    // Close connection
    $conn->close();
    
    error_log("Queue generated: $queueNumber (Type: $queueType) with tracking URL: $trackingUrl");
    
    // Clear session data after successful generation
    unset($_SESSION['fullname'], $_SESSION['studentid'], $_SESSION['yearlevel'], 
          $_SESSION['courseprogram'], $_SESSION['selected_services'], 
          $_SESSION['priority_group'], $_SESSION['generate_qr']);
    
} catch (Exception $e) {
    error_log("Error in QR.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Check for common errors and provide more specific messages
    $errorMessage = $e->getMessage();
    $userMessage = "An error occurred while generating your queue number. Please try again.";
    
    // Provide more specific error messages for debugging (remove in production if needed)
    if (strpos($errorMessage, "does not exist") !== false) {
        $userMessage = "Database table missing. Please contact administrator. Error: " . htmlspecialchars($errorMessage);
    } elseif (strpos($errorMessage, "Connection") !== false) {
        $userMessage = "Database connection error. Please try again later.";
    } elseif (strpos($errorMessage, "prepare") !== false || strpos($errorMessage, "execute") !== false) {
        $userMessage = "Database query error. Please try again or contact administrator.";
    }
    
    // For production, you might want to hide detailed errors
    // For debugging, show the actual error message
    die($userMessage);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>Queue Number Generated - SeQueueR</title>
    <link rel="icon" type="image/png" href="/Frontend/favicon.php">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .success-animation { animation: bounceIn 0.6s ease-out; }
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
        .fade-in { animation: fadeInUp 0.8s ease-out; }
        @keyframes fadeInUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-gradient-to-r from-white via-slate-200 to-sky-500">
    <?php include 'Header.php'; ?>
    
    <main class="flex-grow flex items-center justify-center px-4 py-6 relative overflow-hidden">
        <img alt="Background" aria-hidden="true" class="absolute inset-0 w-full h-full object-cover opacity-20 pointer-events-none select-none" src="/Frontend/Assests/Background.png"/>
        <div class="bg-white rounded-lg shadow-lg max-w-xl w-full p-8 text-center relative z-10" style="box-shadow: 0 8px 24px rgb(0 0 0 / 0.1);">
            
            <!-- Success Icon -->
            <div class="flex justify-center mb-6">
                <div class="bg-yellow-100 rounded-full p-4 success-animation">
                    <i class="fas fa-check text-blue-600 text-2xl"></i>
                </div>
            </div>
            
            <!-- Success Message -->
            <h2 class="text-blue-900 font-extrabold text-xl mb-2 fade-in">
                Your queue number has been successfully generated!
            </h2>
            
            <hr class="border-slate-200 mb-6"/>
            
            <!-- Queue Number -->
            <div class="mb-6 fade-in">
                <h3 class="text-blue-900 font-bold text-lg mb-2">Your Queue Number</h3>
                <div class="text-4xl font-black mb-2" style="color: <?php echo $queueType === 'priority' ? '#FFD700' : '#0f172a'; ?>">
                    <?php echo htmlspecialchars($queueNumber); ?>
                </div>
                <?php if ($queueType === 'priority'): ?>
                <div class="inline-block bg-white-100 text-yellow-400 px-3 py-1 rounded-full text-xs font-semibold mb-2">
                    <i class="fas fa-star"></i> PRIORITY QUEUE
                </div>
                <?php endif; ?>
            </div>
            
            <!-- QR Code -->
            <div class="mb-6 fade-in">
                <div class="bg-white border-2 border-slate-200 rounded-lg p-4 inline-block">
                    <img src="<?php echo htmlspecialchars($qrCodeImageUrl); ?>" 
                         alt="QR Code for Queue <?php echo htmlspecialchars($queueNumber); ?>" 
                         class="w-48 h-48 mx-auto"/>
                </div>
                <p class="text-slate-600 text-sm mt-3">
                    <i class="fas fa-mobile-alt"></i> Scan to track your queue status on mobile
                </p>
                <div class="mt-2">
                    <a href="<?php echo htmlspecialchars($trackingUrl); ?>" 
                       class="text-blue-600 hover:text-blue-800 text-xs underline"
                       target="_blank">
                        Or click here to open on this device
                    </a>
                </div>
            </div>
            
            <!-- Queue Details -->
            <div class="bg-slate-50 rounded-lg p-4 mb-6 text-left fade-in">
                <h4 class="text-blue-900 font-semibold text-sm mb-3">Queue Details</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-600">Student:</span>
                        <span class="text-slate-900 font-medium"><?php echo htmlspecialchars($studentData['full_name']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">ID:</span>
                        <span class="text-slate-900 font-medium"><?php echo htmlspecialchars($studentData['student_id']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Services:</span>
                        <span class="text-slate-900 font-medium"><?php echo count($selectedServices); ?> selected</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Priority:</span>
                        <span class="text-slate-900 font-medium">
                            <?php echo $priorityGroup === 'yes' ? 'Priority' : 'Regular'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex flex-col gap-3 fade-in">
                <button onclick="downloadQR()" 
                        class="w-full bg-blue-600 text-white rounded-md py-3 text-sm hover:bg-blue-700 transition flex items-center justify-center gap-2">
                    <i class="fas fa-download"></i>
                    Download QR Code
                </button>
                <button onclick="window.location.href='https://qmscharlie.byethost5.com/index.php'"
                        class="w-full bg-blue-900 text-white rounded-md py-3 text-sm hover:bg-blue-800 transition flex items-center justify-center gap-2">
                    <i class="fas fa-check"></i>
                    Finish
                </button>
            </div>
        </div>
    </main>
    
    <?php include '../Footer.php'; ?>

    <script>
        const queueNumber = '<?php echo addslashes($queueNumber); ?>';
        const queueType = '<?php echo $queueType; ?>';
        const qrCodeUrl = '<?php echo addslashes($qrCodeImageUrl); ?>';
        
        console.log('Queue generated:', queueNumber, '(Type:', queueType, ')');
        console.log('QR Code generated: Yes');
        console.log('Tracking URL:', '<?php echo addslashes($trackingUrl); ?>');
        
        function downloadQR() {
            // Create a temporary link to download the QR code
            const link = document.createElement('a');
            link.href = qrCodeUrl;
            link.download = 'queue-' + queueNumber + '.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>