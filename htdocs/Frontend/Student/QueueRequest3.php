<?php
session_start();
require_once 'db_config.php'; // Use the new db_config.php instead of db_connect.php

// Service information for popups
$serviceInfo = [
    'good-moral' => [
        'title' => 'Request for Good Moral Certificate',
        'required_documents' => [
            'Valid Student ID Card',
            'Accomplished Request Form',
            'Certificate of Registration (current semester)',
            'Clearance from previous semester',
            'Two copies of 2×2 ID picture',
            'Parent\'s consent form (for minors)'
        ]
    ],
    'insurance-payment' => [
        'title' => 'Insurance Payment',
        'required_documents' => [
            'Valid Student ID Card',
            'Accomplished Request Form',
            'Certificate of Registration (current semester)',
            'Insurance payment slip',
            'Two copies of 2×2 ID picture'
        ]
    ],
    'approval-letter' => [
        'title' => 'Submission of Approval/Transmittal Letter',
        'required_documents' => [
            'Valid Student ID Card',
            'Accomplished Request Form',
            'Certificate of Registration (current semester)',
            'Original approval/transmittal letter',
            'Two copies of 2×2 ID picture'
        ]
    ],
    'temporary-gate-pass' => [
        'title' => 'Request for Temporary Gate Pass',
        'required_documents' => [
            'Valid Student ID Card',
            'Accomplished Request Form',
            'Certificate of Registration (current semester)',
            'Valid reason for gate pass',
            'Two copies of 2×2 ID picture'
        ]
    ],
    'uniform-exemption' => [
        'title' => 'Request for Uniform Exemption',
        'required_documents' => [
            'Valid Student ID Card',
            'Accomplished Request Form',
            'Certificate of Registration (current semester)',
            'Medical certificate (if applicable)',
            'Two copies of 2×2 ID picture'
        ]
    ],
    'enrollment-transfer' => [
        'title' => 'Enrollment/Transfer',
        'required_documents' => [
            'Valid Student ID Card',
            'Accomplished Request Form',
            'Certificate of Registration (current semester)',
            'Transfer credentials',
            'Two copies of 2×2 ID picture'
        ]
    ]
];

// Get student data from session (from Step 1)
$studentData = [
    'full_name' => $_SESSION['fullname'] ?? '',
    'student_id' => $_SESSION['studentid'] ?? '',
    'year_level' => $_SESSION['yearlevel'] ?? '',
    'course_program' => $_SESSION['courseprogram'] ?? ''
];

// Get selected services from session (from Step 2)
$selectedServices = $_SESSION['selected_services'] ?? [];

// Handle service removal
if (isset($_GET['remove']) && isset($_GET['index'])) {
    $indexToRemove = (int)$_GET['index'];
    if (isset($selectedServices[$indexToRemove])) {
        unset($selectedServices[$indexToRemove]);
        $selectedServices = array_values($selectedServices); // Re-index array
        $_SESSION['selected_services'] = $selectedServices;
        // Redirect to prevent resubmission
        header('Location: QueueRequest3.php');
        exit;
    }
}

// Handle priority group submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['priority_group']) && !isset($_POST['final_submit'])) {
    $_SESSION['priority_group'] = $_POST['priority_group'];
    $_SESSION['priority_group_details'] = $_POST['priority_group_details'] ?? '';
    
    // Return success response for AJAX
    echo json_encode(['success' => true]);
    exit;
}

// Handle final form submission (after QR code modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_submit'])) {
    // Make sure priority_group is set in session
    if (isset($_POST['priority_group'])) {
        $_SESSION['priority_group'] = $_POST['priority_group'];
    }
    
    $_SESSION['generate_qr'] = $_POST['generate_qr'] ?? 'no';
    
    // Debug: Log what we're about to process
    error_log("Processing queue request - Priority: " . ($_SESSION['priority_group'] ?? 'not set'));
    
    // Redirect based on QR preference
    if ($_POST['generate_qr'] === 'yes') {
        header('Location: QR.php');
        exit;
    } else {
        header('Location: QueueNumber.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>Queue Number Request - Review</title>
    <link rel="icon" type="image/png" href="/Frontend/favicon.php">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body { 
            font-family: 'Poppins', sans-serif;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('../Assests/QueueReqPic.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: -1;
        }
        .radio-circle { border-radius: 50% !important; aspect-ratio: 1; }
        .modal { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .modal-backdrop { animation: fadeInBackdrop 0.3s ease-out; }
        @keyframes fadeInBackdrop { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <?php include 'Header.php'; ?>
    
    <main class="flex-grow flex items-start justify-center pt-20 pb-20 relative overflow-hidden">
        <div class="relative z-10 bg-white rounded-lg shadow-lg max-w-xl w-full p-8" style="box-shadow: 0 8px 24px rgb(0 0 0 / 0.1);">
            <div class="flex justify-center mb-6">
                <div class="bg-yellow-100 rounded-full p-4">
                    <i class="fas fa-ticket-alt text-yellow-400 text-xl"></i>
                </div>
            </div>
            <h2 class="text-blue-900 font-extrabold text-xl text-center mb-2">Request Your Queue Number</h2>
            <p class="text-center text-slate-600 mb-6 text-sm">Please provide the following information to get your queue number</p>
            
            <div class="flex items-center justify-between text-xs md:text-sm mb-4">
                <span class="font-semibold text-blue-900">Step 3 of 3</span>
                <span class="text-gray-500">Review & Submit</span>
            </div>
            <div class="w-full h-1 rounded-full bg-slate-300 mb-6 relative">
                <div class="h-1 rounded-full bg-yellow-400 w-full"></div>
            </div>
            <hr class="border-slate-200 mb-6"/>
            
            <form action="QueueRequest3.php" method="POST" id="reviewForm">
                <!-- Student Information Section -->
                <h3 class="text-blue-900 font-semibold mb-4 text-sm">Student Information</h3>
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Full Name</label>
                        <p class="text-sm text-slate-900"><?php echo htmlspecialchars($studentData['full_name']); ?></p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Student ID Number</label>
                        <p class="text-sm text-slate-900"><?php echo htmlspecialchars($studentData['student_id']); ?></p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Year Level</label>
                        <p class="text-sm text-slate-900"><?php echo htmlspecialchars($studentData['year_level']); ?></p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Course/Program</label>
                        <p class="text-sm text-slate-900"><?php echo htmlspecialchars($studentData['course_program']); ?></p>
                    </div>
                </div>
                
                <!-- Selected Services Section -->
                <h3 class="text-blue-900 font-semibold mb-4 text-sm">Selected Services</h3>
                <div class="space-y-3 mb-8">
                    <?php if (empty($selectedServices)): ?>
                    <div class="bg-white border border-slate-200 rounded-lg p-4 text-center shadow-sm">
                        <p class="text-slate-500 text-sm">No services selected. Please go back to Step 2 to select services.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($selectedServices as $index => $serviceKey): ?>
                    <div class="bg-white border border-slate-200 rounded-lg p-4 flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-slate-900 font-medium"><?php echo htmlspecialchars($serviceInfo[$serviceKey]['title'] ?? $serviceKey); ?></span>
                            <button type="button" class="w-5 h-5 bg-blue-700 rounded-full flex items-center justify-center hover:bg-blue-800 transition" 
                                    onclick="showServiceInfo('<?php echo htmlspecialchars($serviceKey); ?>')">
                                <i class="fas fa-info text-white text-xs"></i>
                            </button>
                        </div>
                        <button type="button" class="flex items-center justify-center hover:opacity-70 transition" 
                                onclick="removeService(<?php echo $index; ?>)">
                            <i class="fas fa-trash text-red-500 text-sm"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-center gap-8">
                    <button class="flex items-center gap-2 border border-slate-300 rounded-md text-slate-700 text-sm hover:bg-slate-100 transition font-medium px-6 py-3 min-w-[120px] justify-center" 
                            type="button" onclick="window.location.href='QueueRequest2.php'">
                        <i class="fas fa-arrow-left text-sm"></i>
                        Back
                    </button>
                    <button class="bg-blue-900 text-white rounded-md text-sm hover:bg-blue-800 transition flex items-center justify-center gap-2 font-medium px-6 py-3 min-w-[120px]" 
                            type="button" onclick="showDocumentsModal()">
                        Next
                        <i class="fas fa-arrow-right text-sm"></i>
                    </button>
                </div>
            </form>
        </div>
    </main>
    
    <?php include '../Footer.php'; ?>

    <!-- Service Information Modal -->
    <div id="serviceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden modal-backdrop z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-md w-full p-6 modal">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalTitle" class="text-lg font-semibold text-slate-900"></h3>
                <button type="button" onclick="closeServiceInfo()" class="text-slate-400 hover:text-slate-600 transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <div class="bg-yellow-100 rounded-full p-2">
                        <i class="fas fa-check text-yellow-400 text-sm"></i>
                    </div>
                    <h4 class="font-semibold text-slate-900">Required Documents</h4>
                </div>
                <ul id="modalDocuments" class="space-y-2 text-sm text-slate-700">
                    <!-- Documents will be populated by JavaScript -->
                </ul>
            </div>
            <div class="flex justify-end">
                <button type="button" onclick="closeServiceInfo()" 
                        class="bg-blue-900 text-white px-6 py-2 rounded-md text-sm hover:bg-blue-800 transition">
                    Got It
                </button>
            </div>
        </div>
    </div>

    <!-- Required Documents Confirmation Modal -->
    <div id="documentsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden modal-backdrop z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-md w-full p-6 modal" style="border-radius: 8px;">
            <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-200">
                <h3 id="documentsModalTitle" class="text-lg font-bold text-blue-900"></h3>
                <button type="button" onclick="closeDocumentsModal()" class="text-gray-500 hover:text-gray-700 transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-4">
                    <div class="bg-yellow-100 rounded-full p-2">
                        <i class="fas fa-check text-yellow-400 text-sm"></i>
                    </div>
                    <h4 class="font-semibold text-blue-900">Required Documents</h4>
                </div>
                <ul id="documentsModalList" class="space-y-2 text-sm text-gray-700 pl-0">
                    <!-- Documents will be populated by JavaScript -->
                </ul>
            </div>
            <div class="flex justify-center">
                <button type="button" onclick="handleDocumentsGotIt()" 
                        class="bg-blue-900 text-white px-6 py-3 rounded-md text-sm hover:bg-blue-800 transition font-medium w-full">
                    Got It
                </button>
            </div>
        </div>
    </div>

    <!-- Priority Group Modal -->
    <div id="priorityModal" class="fixed inset-0 bg-black bg-opacity-50 hidden modal-backdrop z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-md w-full p-6 modal">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-blue-900">Do you belong to the priority group?</h3>
                <button type="button" onclick="closePriorityModal()" class="text-slate-400 hover:text-slate-600 transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <p class="text-sm text-gray-600 mb-6">Priority groups receive expedited processing</p>
            
            <form id="priorityForm">
                <input type="hidden" name="priority_group" id="priorityGroupValue" value="yes">
                <div class="space-y-4 mb-6">
                    <!-- Yes Option -->
                    <label class="block cursor-pointer">
                        <input type="radio" name="priority_option" value="yes" class="sr-only" id="priority-yes" checked>
                        <div class="border-2 border-green-500 bg-green-50 rounded-lg p-4 hover:border-green-300 transition" id="yes-option">
                            <div class="flex items-center gap-3">
                                <div class="w-5 h-5 border-2 border-green-500 flex items-center justify-center radio-circle" id="yes-circle">
                                    <div class="w-3 h-3 bg-green-500 rounded-full" id="yes-dot"></div>
                                </div>
                                <div>
                                    <div class="font-medium text-slate-900">Yes</div>
                                    <div class="text-sm text-gray-600">I belong to a priority group (PWD, Senior Citizen, Pregnant, etc.)</div>
                                </div>
                            </div>
                        </div>
                    </label>
                    
                    <!-- No Option -->
                    <label class="block cursor-pointer">
                        <input type="radio" name="priority_option" value="no" class="sr-only" id="priority-no">
                        <div class="border-2 border-red-200 rounded-lg p-4 hover:border-red-300 transition" id="no-option">
                            <div class="flex items-center gap-3">
                                <div class="w-5 h-5 border-2 border-red-500 flex items-center justify-center radio-circle" id="no-circle">
                                    <div class="w-3 h-3 bg-red-500 rounded-full hidden" id="no-dot"></div>
                                </div>
                                <div>
                                    <div class="font-medium text-slate-900">No</div>
                                    <div class="text-sm text-gray-600">I am requesting regular service</div>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closePriorityModal()" 
                            class="border border-blue-500 text-blue-500 rounded-md text-sm hover:bg-blue-50 transition font-medium px-6 py-2 min-w-[100px]">
                        Cancel
                    </button>
                    <button type="button" onclick="submitPriorityForm()" 
                            class="bg-blue-900 text-white rounded-md text-sm hover:bg-blue-800 transition font-medium px-6 py-2 min-w-[100px]">
                        Continue
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div id="qrCodeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden modal-backdrop z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-md w-full p-6 modal">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-slate-900"></h3>
                <button type="button" onclick="closeQrCodeModal()" class="text-slate-400 hover:text-slate-600 transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="flex flex-col items-center mb-6">
                <div class="bg-yellow-100 rounded-full p-4 mb-4">
                    <i class="fas fa-qrcode text-yellow-400 text-xl"></i>
                </div>
                <h3 class="text-blue-900 font-extrabold text-xl text-center mb-2">Would you like to receive a QR code for mobile access?</h3>
                <p class="text-center text-slate-600 text-sm">Scan the QR code on your mobile device to track your queue status anytime</p>
            </div>

            <form id="qrCodeForm" method="POST" action="QueueRequest3.php">
                <input type="hidden" name="final_submit" value="1">
                <input type="hidden" name="priority_group" id="finalPriorityGroup" value="">
                <input type="hidden" name="generate_qr" id="generateQrValue" value="yes">
                
                <div class="space-y-4 mb-6">
                    <!-- Yes, Generate QR Code Option -->
                    <label class="block cursor-pointer">
                        <input type="radio" name="qr_option" value="yes" class="sr-only" id="qr-yes" checked>
                        <div class="border-2 border-green-500 bg-green-50 rounded-lg p-4 hover:border-green-300 transition" id="qr-yes-option">
                            <div class="flex items-center gap-3">
                                <div class="w-5 h-5 border-2 border-green-500 flex items-center justify-center radio-circle">
                                    <div class="w-3 h-3 bg-green-500 rounded-full" id="qr-yes-dot"></div>
                                </div>
                                <div>
                                    <div class="font-medium text-slate-900">Yes, Generate QR Code</div>
                                    <div class="text-sm text-gray-600">Receive a QR code via email or download to access your queue on mobile</div>
                                </div>
                            </div>
                        </div>
                    </label>

                    <!-- No, Continue Without QR Option -->
                    <label class="block cursor-pointer">
                        <input type="radio" name="qr_option" value="no" class="sr-only" id="qr-no">
                        <div class="border-2 border-red-200 rounded-lg p-4 hover:border-red-300 transition" id="qr-no-option">
                            <div class="flex items-center gap-3">
                                <div class="w-5 h-5 border-2 border-red-500 flex items-center justify-center radio-circle">
                                    <div class="w-3 h-3 bg-red-500 rounded-full hidden" id="qr-no-dot"></div>
                                </div>
                                <div>
                                    <div class="font-medium text-slate-900">No, Continue Without QR</div>
                                    <div class="text-sm text-gray-600">Proceed without mobile access (you can still track via web browser)</div>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>

                <div class="flex justify-center gap-3">
                    <button type="button" onclick="closeQrCodeModal()" 
                            class="border border-blue-500 text-blue-500 rounded-md text-sm hover:bg-blue-50 transition font-medium px-6 py-2 min-w-[120px]">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-900 text-white rounded-md text-sm hover:bg-blue-800 transition font-medium px-6 py-2 min-w-[120px]">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const serviceInfo = <?php echo json_encode($serviceInfo); ?>;
        let selectedPriorityGroup = 'yes'; // Default value
        
        function showServiceInfo(serviceKey) {
            const info = serviceInfo[serviceKey];
            if (!info) return;
            
            document.getElementById('modalTitle').textContent = info.title;
            const documentsList = document.getElementById('modalDocuments');
            documentsList.innerHTML = '';
            
            info.required_documents.forEach(doc => {
                const li = document.createElement('li');
                li.className = 'flex items-start gap-2';
                li.innerHTML = `
                    <span class="text-slate-400 mt-1">•</span>
                    <span>${doc}</span>
                `;
                documentsList.appendChild(li);
            });
            
            document.getElementById('serviceModal').classList.remove('hidden');
        }
        
        function closeServiceInfo() {
            document.getElementById('serviceModal').classList.add('hidden');
        }
        
        function removeService(index) {
            if (confirm('Are you sure you want to remove this service?')) {
                window.location.href = 'QueueRequest3.php?remove=1&index=' + index;
            }
        }
        
        // Documents Modal Functions
        function showDocumentsModal() {
            const selectedServices = <?php echo json_encode($selectedServices); ?>;
            const serviceInfo = <?php echo json_encode($serviceInfo); ?>;
            
            if (selectedServices.length === 0) {
                // No services selected, go directly to priority modal
                showPriorityModal();
                return;
            }
            
            // Determine title - use first service or show combined
            let modalTitle = '';
            if (selectedServices.length === 1) {
                const serviceKey = selectedServices[0];
                modalTitle = serviceInfo[serviceKey]?.title || serviceKey;
            } else {
                // Multiple services - show generic title or first service
                const firstServiceKey = selectedServices[0];
                modalTitle = serviceInfo[firstServiceKey]?.title || 'Required Documents';
            }
            
            // Collect all unique documents from all selected services
            const allDocuments = new Set();
            selectedServices.forEach(serviceKey => {
                const docs = serviceInfo[serviceKey]?.required_documents || [];
                docs.forEach(doc => allDocuments.add(doc));
            });
            
            // Set modal title
            document.getElementById('documentsModalTitle').textContent = modalTitle;
            
            // Populate documents list with blue bullet points
            const documentsList = document.getElementById('documentsModalList');
            documentsList.innerHTML = '';
            
            Array.from(allDocuments).forEach(doc => {
                const li = document.createElement('li');
                li.className = 'flex items-start gap-3';
                li.innerHTML = `
                    <span class="inline-block w-2 h-2 rounded-full bg-blue-900 mt-2 flex-shrink-0"></span>
                    <span class="text-gray-700">${doc}</span>
                `;
                documentsList.appendChild(li);
            });
            
            // Show the modal
            document.getElementById('documentsModal').classList.remove('hidden');
        }
        
        function closeDocumentsModal() {
            document.getElementById('documentsModal').classList.add('hidden');
        }
        
        function handleDocumentsGotIt() {
            closeDocumentsModal();
            // Show priority modal after documents modal is closed
            showPriorityModal();
        }
        
        // Priority Modal Functions
        function showPriorityModal() {
            document.getElementById('priorityModal').classList.remove('hidden');
        }
        
        function closePriorityModal() {
            document.getElementById('priorityModal').classList.add('hidden');
        }
        
        // Priority Form Submission
        function submitPriorityForm() {
            const form = document.getElementById('priorityForm');
            const formData = new FormData(form);
            
            // Get the selected priority group value
            const priorityOption = document.querySelector('input[name="priority_option"]:checked').value;
            selectedPriorityGroup = priorityOption;
            
            // Update the hidden input value
            document.getElementById('priorityGroupValue').value = priorityOption;
            
            console.log('Selected priority group:', selectedPriorityGroup); // Debug log
            
            // Submit to session via AJAX
            formData.set('priority_group', priorityOption);
            
            fetch('QueueRequest3.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closePriorityModal();
                    showQrCodeModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Continue anyway
                closePriorityModal();
                showQrCodeModal();
            });
        }
        
        // QR Code Modal Functions
        function showQrCodeModal() {
            // Set the priority group value in the final form
            document.getElementById('finalPriorityGroup').value = selectedPriorityGroup;
            console.log('Opening QR modal with priority:', selectedPriorityGroup); // Debug log
            
            closePriorityModal();
            document.getElementById('qrCodeModal').classList.remove('hidden');
        }
        
        function closeQrCodeModal() {
            document.getElementById('qrCodeModal').classList.add('hidden');
        }
        
        // Handle radio button selection for Priority Modal
        document.addEventListener('DOMContentLoaded', function() {
            const yesRadio = document.getElementById('priority-yes');
            const noRadio = document.getElementById('priority-no');
            const yesOption = document.getElementById('yes-option');
            const noOption = document.getElementById('no-option');
            const yesDot = document.getElementById('yes-dot');
            const noDot = document.getElementById('no-dot');
            
            yesOption.addEventListener('click', function() {
                yesRadio.checked = true;
                noRadio.checked = false;
                selectedPriorityGroup = 'yes';
                updateVisualSelection();
            });
            
            noOption.addEventListener('click', function() {
                noRadio.checked = true;
                yesRadio.checked = false;
                selectedPriorityGroup = 'no';
                updateVisualSelection();
            });
            
            function updateVisualSelection() {
                if (yesRadio.checked) {
                    yesOption.classList.add('border-green-500', 'bg-green-50');
                    yesOption.classList.remove('border-green-200');
                    yesDot.classList.remove('hidden');
                    noOption.classList.remove('border-red-500', 'bg-red-50');
                    noOption.classList.add('border-red-200');
                    noDot.classList.add('hidden');
                } else if (noRadio.checked) {
                    noOption.classList.add('border-red-500', 'bg-red-50');
                    noOption.classList.remove('border-red-200');
                    noDot.classList.remove('hidden');
                    yesOption.classList.remove('border-green-500', 'bg-green-50');
                    yesOption.classList.add('border-green-200');
                    yesDot.classList.add('hidden');
                }
            }
            
            // Handle QR code modal radio button selection
            const qrYesRadio = document.getElementById('qr-yes');
            const qrNoRadio = document.getElementById('qr-no');
            const qrYesOption = document.getElementById('qr-yes-option');
            const qrNoOption = document.getElementById('qr-no-option');
            const qrYesDot = document.getElementById('qr-yes-dot');
            const qrNoDot = document.getElementById('qr-no-dot');
            const generateQrValue = document.getElementById('generateQrValue');
            
            qrYesOption.addEventListener('click', function() {
                qrYesRadio.checked = true;
                qrNoRadio.checked = false;
                generateQrValue.value = 'yes';
                updateQrVisualSelection();
            });
            
            qrNoOption.addEventListener('click', function() {
                qrNoRadio.checked = true;
                qrYesRadio.checked = false;
                generateQrValue.value = 'no';
                updateQrVisualSelection();
            });
            
            function updateQrVisualSelection() {
                if (qrYesRadio.checked) {
                    qrYesOption.classList.add('border-green-500', 'bg-green-50');
                    qrYesOption.classList.remove('border-green-200');
                    qrYesDot.classList.remove('hidden');
                    qrNoOption.classList.remove('border-red-500', 'bg-red-50');
                    qrNoOption.classList.add('border-red-200');
                    qrNoDot.classList.add('hidden');
                } else if (qrNoRadio.checked) {
                    qrNoOption.classList.add('border-red-500', 'bg-red-50');
                    qrNoOption.classList.remove('border-red-200');
                    qrNoDot.classList.remove('hidden');
                    qrYesOption.classList.remove('border-green-500', 'bg-green-50');
                    qrYesOption.classList.add('border-green-200');
                    qrYesDot.classList.add('hidden');
                }
            }
            
            updateQrVisualSelection();
        });
        
        // Close modals when clicking outside
        document.getElementById('serviceModal').addEventListener('click', function(e) {
            if (e.target === this) closeServiceInfo();
        });
        
        document.getElementById('documentsModal').addEventListener('click', function(e) {
            if (e.target === this) closeDocumentsModal();
        });
        
        document.getElementById('priorityModal').addEventListener('click', function(e) {
            if (e.target === this) closePriorityModal();
        });
        
        document.getElementById('qrCodeModal').addEventListener('click', function(e) {
            if (e.target === this) closeQrCodeModal();
        });
    </script>
</body>
</html>