<?php
// Queue Management Dashboard for SeQueueR
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// DEBUG: show assigned counter number (remove after testing)
echo 'DEBUG Counter: ' . ($_SESSION['user']['counterNumber'] ?? 'none') . '<br>';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Management - SeQueueR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Working Header -->
    <?php include 'Header.php'; ?>
    
    <!-- Main Content -->
    <main class="bg-gray-100 min-h-screen">
        <div class="py-8 px-6 md:px-10 mx-4 md:mx-8 lg:mx-12">
            <div class="grid grid-cols-1 lg:grid-cols-10 gap-8">
                <!-- Left Panel - Current Queue Details -->
                <div class="lg:col-span-7 space-y-6">
                    <!-- Currently Serving Card -->
                    <div class="bg-white border-2 border-yellow-600 rounded-lg p-8 text-center shadow-sm">
                        <div class="text-6xl font-bold text-yellow-600 mb-3" id="currentQueueNumber">--</div>
                        <div class="flex items-center justify-center space-x-2">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <span class="text-green-600 font-medium">Currently Serving</span>
                        </div>
                    </div>

                    <!-- Student Information & Queue Details Card -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Student Information -->
                            <div>
                                <h3 class="text-lg font-bold text-blue-800 mb-6 pb-2 border-b border-gray-200">Student Information</h3>
                                <div class="space-y-4">
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Full Name</span>
                                        <p class="font-bold text-gray-800 text-base" id="studentName">--</p>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Student ID</span>
                                        <p class="font-bold text-gray-800 text-base" id="studentId">--</p>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Course</span>
                                        <p class="font-bold text-gray-800 text-base" id="studentCourse">--</p>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Year Level</span>
                                        <p class="font-bold text-gray-800 text-base" id="studentYear">--</p>
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
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-yellow-200 text-gray-800">
                                                <i class="fas fa-star mr-2 text-black"></i>
                                                Priority
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Time Requested</span>
                                        <p class="font-bold text-gray-800 text-base" id="timeRequested">--</p>
                                    </div>
                                    <div>
                                        <span class="text-sm text-gray-600 block mb-1">Total Wait Time</span>
                                        <p class="font-bold text-gray-800 text-base" id="waitTime">--</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Requested Services -->
                    <div id="servicesContainer" class="bg-white border border-gray-200 rounded-lg p-6 shadow-sm">
                        <h3 class="text-lg font-bold text-blue-800 mb-6">Requested Services</h3>
                        
                        <!-- Services will be populated dynamically -->
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-clipboard-list text-4xl mb-4"></i>
                            <p class="text-lg font-medium">No services requested</p>
                            <p class="text-sm">Services will appear here when a student requests them</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex space-x-4">
                        <button class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg flex items-center justify-center space-x-2">
                            <i class="fas fa-arrow-right"></i>
                            <span>COMPLETE & NEXT</span>
                        </button>
                        <button class="flex-1 bg-yellow-400 hover:bg-yellow-500 text-black font-semibold py-3 px-6 rounded-lg flex items-center justify-center space-x-2">
                            <i class="fas fa-pause"></i>
                            <span>MARK AS STALLED</span>
                        </button>
                        <button class="flex-1 bg-blue-900 hover:bg-blue-800 text-white font-semibold py-3 px-6 rounded-lg flex items-center justify-center space-x-2">
                            <i class="fas fa-forward"></i>
                            <span>SKIP QUEUE</span>
                        </button>
                    </div>
                </div>

                <!-- Right Panel - Queue Lists -->
                <div class="lg:col-span-3 space-y-6">
                     <!-- Queue List -->
                     <div class="bg-white border border-gray-200 rounded-lg">
                         <!-- Header -->
                         <div class="flex justify-between items-center px-5 py-3 border-b border-gray-200">
                             <h3 class="text-lg font-bold text-blue-900">Queue List</h3>
                             <div class="bg-blue-900 text-white rounded-full w-8 h-8 flex items-center justify-center text-xs font-semibold queue-total-count">0</div>
                         </div>
                         
                         <!-- Active Queue -->
                         <div class="border-b border-gray-200">
                             <button class="group flex justify-between items-center w-full px-5 py-3 bg-blue-50 focus:outline-none" onclick="toggleQueueSection('activeQueue')">
                                 <div class="flex items-center space-x-2">
                                     <i class="fas fa-question-circle text-blue-600 w-4 h-4"></i>
                                     <h4 class="font-semibold text-blue-900 text-sm">Active Queue</h4>
                                     <div class="bg-blue-900 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-semibold queue-active-count">0</div>
                                 </div>
                                 <i class="fas fa-chevron-down text-blue-900 w-4 h-4 transition-transform" id="activeQueue-arrow"></i>
                             </button>
                             <!-- Active queue items -->
                             <div id="activeQueue-content" class="divide-y divide-gray-200">
                                 <!-- Queue items will be populated dynamically -->
                                 <div class="px-5 py-8 text-center text-gray-500">
                                     <i class="fas fa-users text-3xl mb-2"></i>
                                     <p>No active queue items</p>
                                 </div>
                             </div>
                         </div>

                        <!-- Stalled Queue -->
                        <div class="border-b border-gray-200">
                            <button class="group flex justify-between items-center w-full px-5 py-3 bg-yellow-50 focus:outline-none" onclick="toggleQueueSection('stalledQueue')">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-exclamation-triangle text-yellow-500 w-4 h-4"></i>
                                    <h4 class="font-semibold text-yellow-600 text-sm">Stalled Queue</h4>
                                    <div class="bg-yellow-400 text-yellow-900 rounded-full w-6 h-6 flex items-center justify-center text-xs font-semibold queue-stalled-count">0</div>
                                </div>
                                <i class="fas fa-chevron-down text-yellow-600 w-4 h-4 transition-transform" id="stalledQueue-arrow"></i>
                            </button>
                            <!-- Stalled items -->
                            <div id="stalledQueue-content" class="divide-y divide-gray-200">
                                <!-- Stalled items will be populated dynamically -->
                                <div class="px-5 py-8 text-center text-gray-500">
                                    <i class="fas fa-exclamation-triangle text-3xl mb-2"></i>
                                    <p>No stalled queue items</p>
                                </div>
                            </div>
                        </div>

                        <!-- Skipped Queue -->
                        <div class="border-b border-red-200">
                            <button class="group flex justify-between items-center w-full px-5 py-3 bg-red-50 hover:bg-red-100 focus:outline-none" onclick="toggleQueueSection('skippedQueue')">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-times-circle text-red-600 w-4 h-4"></i>
                                    <h4 class="font-semibold text-red-600 text-sm">Skipped Queue</h4>
                                    <div class="bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-semibold queue-skipped-count">0</div>
                                </div>
                                <i class="fas fa-chevron-down text-red-600 w-4 h-4 transition-transform" id="skippedQueue-arrow"></i>
                            </button>
                            <div id="skippedQueue-content" class="divide-y divide-gray-200">
                                <!-- Skipped items will be populated dynamically -->
                                <div class="px-5 py-8 text-center text-gray-500">
                                    <i class="fas fa-times-circle text-3xl mb-2"></i>
                                    <p>No skipped queue items</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Transaction Status -->
                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Today's Transaction Status</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-clock text-blue-600 text-2xl mb-2"></i>
                                <p class="text-2xl font-bold text-gray-900">--</p>
                                <p class="text-sm text-gray-600">Avg Service Time</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-check-circle text-green-600 text-2xl mb-2"></i>
                                <p class="text-2xl font-bold text-gray-900">0</p>
                                <p class="text-sm text-gray-600">Completed</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-pause-circle text-yellow-600 text-2xl mb-2"></i>
                                <p class="text-2xl font-bold text-gray-900">0</p>
                                <p class="text-sm text-gray-600">Stalled</p>
                            </div>
                            <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <i class="fas fa-times-circle text-red-600 text-2xl mb-2"></i>
                                <p class="text-2xl font-bold text-gray-900">0</p>
                                <p class="text-sm text-gray-600">Cancelled</p>
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
        // Backend-ready JavaScript for Queue Management
        let currentQueue = null;
        let queueList = [];
        
        // Initialize the interface
        document.addEventListener('DOMContentLoaded', function() {
            loadQueueData();
            setupEventListeners();
        });
        
        // Load queue data from backend
        function loadQueueData() {
            fetch('queue_api.php?action=get_current_queue')
                .then(response => {
                    // If unauthorized (401), just load empty data instead of redirecting to Signin
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
            updateQueueList(emptyData.queueList);
            updateStatistics(emptyData.statistics);
        }
        
        // Update current queue display
        function updateCurrentQueue(queue) {
            currentQueue = queue;
            if (queue) {
                document.getElementById('currentQueueNumber').textContent = queue.queue_number || '--';
                document.getElementById('studentName').textContent = queue.student_name || '--';
                document.getElementById('studentId').textContent = queue.student_id || '--';
                document.getElementById('studentCourse').textContent = queue.course_program || '--';
                document.getElementById('studentYear').textContent = queue.year_level || '--';
                document.getElementById('timeRequested').textContent = queue.time_requested || '--';
                document.getElementById('waitTime').textContent = queue.wait_time || '--';
                
                // Update priority type
                const priorityType = document.getElementById('priorityType');
                if (priorityType) {
                    if (queue.queue_type === 'priority') {
                        priorityType.innerHTML = '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-yellow-200 text-gray-800"><i class="fas fa-star mr-2 text-black"></i>Priority</span>';
                    } else {
                        priorityType.innerHTML = '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-blue-200 text-gray-800">Regular</span>';
                    }
                }
                
                // Update services
                const servicesContainer = document.getElementById('servicesContainer');
                console.log('Services data:', queue.services); // Debug log
                
                // Handle both array and string formats
                let servicesList = [];
                if (queue.services) {
                    if (Array.isArray(queue.services)) {
                        servicesList = queue.services.filter(s => s && s.trim().length > 0);
                    } else if (typeof queue.services === 'string' && queue.services.trim().length > 0) {
                        servicesList = queue.services.split(',').map(s => s.trim()).filter(s => s.length > 0);
                    }
                }
                
                console.log('Services list:', servicesList); // Debug log
                
                if (servicesContainer && servicesList.length > 0) {
                    servicesContainer.innerHTML = `
                        <h3 class="text-lg font-bold text-blue-800 mb-6">Requested Services</h3>
                        ${servicesList.map((serviceCode, index) => {
                            const serviceId = 'service-' + index;
                            // Convert service code to display name
                            const serviceName = getServiceDisplayName(serviceCode.trim());
                            // Define required documents based on service
                            const documents = getRequiredDocuments(serviceCode.trim());
                            return `
                            <div class="border border-gray-200 rounded-lg mb-3">
                                <!-- Service Header with Checkbox and Expand Arrow -->
                                <div class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50" onclick="toggleServiceDetails('${serviceId}')">
                                    <div class="flex items-center space-x-3">
                                        <input type="checkbox" id="${serviceId}-checkbox" class="w-5 h-5 text-gray-400 rounded border-2 border-gray-300" onclick="event.stopPropagation(); toggleAllDocuments('${serviceId}', ${documents.length});" onchange="toggleAllDocuments('${serviceId}', ${documents.length});">
                                        <label for="${serviceId}-checkbox" class="font-semibold text-gray-800 cursor-pointer" onclick="event.stopPropagation();">${serviceName}</label>
                                    </div>
                                    <i class="fas fa-chevron-down text-gray-600 transition-transform" id="${serviceId}-arrow"></i>
                                </div>
                                
                                <!-- Expandable Service Details -->
                                <div id="${serviceId}-details" class="px-4 pb-4 border-t border-gray-200" style="display: block;">
                                    <p class="text-sm text-gray-600 mb-3 font-semibold mt-4">Required Documents</p>
                                    ${documents.map((doc, docIndex) => `
                                        <div class="flex items-center space-x-2 mb-2">
                                            <input type="checkbox" id="${serviceId}-doc-${docIndex}" class="w-4 h-4 text-blue-600 rounded border-2 border-gray-300" onchange="updateDocumentVerification('${serviceId}', ${documents.length})">
                                            <label for="${serviceId}-doc-${docIndex}" class="text-sm text-gray-700">${doc}</label>
                                        </div>
                                    `).join('')}
                                    
                                    <div class="mt-3 border-l-4 border-gray-400 bg-gray-50 px-3 py-2 rounded" id="${serviceId}-verification">
                                        <p class="text-xs text-gray-600 font-medium flex items-center">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            <span id="${serviceId}-count">0 of ${documents.length} documents verified</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            `;
                        }).join('')}
                        
                        <div class="mt-4 border border-gray-200 rounded-lg">
                            <div class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50" onclick="toggleServiceDetails('uniform-exemption')">
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" class="w-5 h-5 text-gray-400 rounded border-2 border-gray-300" onclick="event.stopPropagation();">
                                    <span class="text-sm font-semibold text-gray-800">Request for Uniform Exemption</span>
                                </label>
                                <i class="fas fa-chevron-down text-gray-400" id="uniform-exemption-arrow"></i>
                            </div>
                        </div>
                    `;
                    
                    // Restore checkbox states after rendering
                    setTimeout(() => restoreCheckboxStates(queue.id), 100);
                } else if (servicesContainer) {
                    console.log('No services or invalid services data'); // Debug log
                    servicesContainer.innerHTML = `
                        <h3 class="text-lg font-bold text-blue-800 mb-6">Requested Services</h3>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-clipboard-list text-4xl mb-4"></i>
                            <p class="text-lg font-medium">No services requested</p>
                            <p class="text-sm">Services will appear here when a student requests them</p>
                        </div>
                    `;
                }
            } else {
                // Show empty state
                document.getElementById('currentQueueNumber').textContent = '--';
                document.getElementById('studentName').textContent = '--';
                document.getElementById('studentId').textContent = '--';
                document.getElementById('studentCourse').textContent = '--';
                document.getElementById('studentYear').textContent = '--';
                document.getElementById('timeRequested').textContent = '--';
                document.getElementById('waitTime').textContent = '--';
                
                // Reset services container
                const servicesContainer = document.getElementById('servicesContainer');
                if (servicesContainer) {
                    servicesContainer.innerHTML = `
                        <h3 class="text-lg font-bold text-blue-800 mb-6">Requested Services</h3>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-clipboard-list text-4xl mb-4"></i>
                            <p class="text-lg font-medium">No services requested</p>
                            <p class="text-sm">Services will appear here when a student requests them</p>
                        </div>
                    `;
                }
            }
        }
        
        // Update queue list
        function updateQueueList(waitingQueues, stalledQueues, skippedQueues) {
            // Update counts
            const totalCount = document.querySelector('.queue-total-count');
            const activeCount = document.querySelector('.queue-active-count');
            const stalledCount = document.querySelector('.queue-stalled-count');
            const skippedCount = document.querySelector('.queue-skipped-count');
            
            const total = waitingQueues.length + stalledQueues.length + skippedQueues.length;
            if (totalCount) totalCount.textContent = total;
            if (activeCount) activeCount.textContent = waitingQueues.length;
            if (stalledCount) stalledCount.textContent = stalledQueues.length;
            if (skippedCount) skippedCount.textContent = skippedQueues.length;
            
            // Update active queue content
            const activeContent = document.getElementById('activeQueue-content');
            if (activeContent) {
                if (waitingQueues.length === 0) {
                    activeContent.innerHTML = '<div class="px-5 py-8 text-center text-gray-500"><i class="fas fa-users text-3xl mb-2"></i><p>No active queue items</p></div>';
                } else {
                    activeContent.innerHTML = waitingQueues.map(q => {
                        const isPriority = q.queue_type === 'priority';
                        const priorityIcon = isPriority ? '<i class="fas fa-star text-yellow-500 mr-1"></i>' : '';
                        const queueClass = isPriority ? 'P-' : 'R-';
                        return `
                        <div class="px-5 py-3 hover:bg-blue-50 border-l-4 ${isPriority ? 'border-yellow-400' : 'border-blue-400'}">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <div class="flex items-center mb-1">
                                        ${priorityIcon}
                                        <span class="font-bold text-gray-900">${queueClass}${q.queue_number.replace(/[PR]-/, '')}</span>
                                    </div>
                                    <div class="text-sm text-gray-700 font-medium">${q.student_name}</div>
                                    <div class="text-xs text-gray-500">${q.course_program || ''}</div>
                                </div>
                                <div class="text-right">
                                    <div class="flex items-center text-xs text-blue-600 font-medium">
                                        <i class="far fa-clock mr-1"></i>
                                        ${q.wait_time_minutes} min
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center text-xs text-gray-600">
                                <i class="fas fa-circle text-green-500 mr-1" style="font-size: 6px;"></i>
                                Waiting in queue
                            </div>
                        </div>
                        `;
                    }).join('');
                }
            }
            
            // Update stalled queue content
            const stalledContent = document.getElementById('stalledQueue-content');
            if (stalledContent) {
                if (stalledQueues.length === 0) {
                    stalledContent.innerHTML = '<div class="px-5 py-8 text-center text-gray-500"><i class="fas fa-exclamation-triangle text-3xl mb-2"></i><p>No stalled queue items</p></div>';
                } else {
                    stalledContent.innerHTML = stalledQueues.map(q => {
                        const serviceCode = q.services ? q.services.split(', ')[0] : 'Service';
                        const services = getServiceDisplayName(serviceCode.trim());
                        return `
                        <div class="px-5 py-3 hover:bg-yellow-50 border-l-4 border-yellow-400">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <div class="font-bold text-gray-900 mb-1">${q.queue_number}</div>
                                    <div class="text-sm text-gray-700 font-medium">${q.student_name}</div>
                                    <div class="text-xs text-gray-600">${services}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-red-600 font-medium">
                                        <i class="far fa-clock mr-1"></i>
                                        ${q.wait_time_minutes || 0} min
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center text-xs text-yellow-600">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    Missing Documents
                                </div>
                                <button onclick="resumeQueue(${q.id})" class="bg-blue-900 text-white px-3 py-1 rounded text-xs font-medium hover:bg-blue-800 flex items-center">
                                    <i class="fas fa-play mr-1"></i>
                                    Resume
                                </button>
                            </div>
                        </div>
                        `;
                    }).join('');
                }
            }
            
            // Update skipped queue content
            const skippedContent = document.getElementById('skippedQueue-content');
            if (skippedContent) {
                if (skippedQueues.length === 0) {
                    skippedContent.innerHTML = '<div class="px-5 py-8 text-center text-gray-500"><i class="fas fa-times-circle text-3xl mb-2"></i><p>No skipped queue items</p></div>';
                } else {
                    skippedContent.innerHTML = skippedQueues.map(q => {
                        const serviceCode = q.services ? q.services.split(', ')[0] : 'Service';
                        const services = getServiceDisplayName(serviceCode.trim());
                        const waitTime = q.wait_time_minutes || 0;
                        const remainingTime = 60 - waitTime;
                        const isNearExpiry = remainingTime <= 15 && remainingTime > 0;
                        const isExpired = remainingTime <= 0;
                        
                        let statusMessage = 'Not present';
                        let statusClass = 'text-red-600';
                        let borderClass = 'border-red-400';
                        
                        if (isExpired) {
                            statusMessage = 'Auto-cancelling soon';
                            statusClass = 'text-red-800';
                            borderClass = 'border-red-600';
                        } else if (isNearExpiry) {
                            statusMessage = `${remainingTime} min until auto-cancel`;
                            statusClass = 'text-orange-600';
                            borderClass = 'border-orange-400';
                        }
                        
                        return `
                        <div class="px-5 py-3 hover:bg-red-50 border-l-4 ${borderClass}">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <div class="font-bold text-gray-900 mb-1">${q.queue_number}</div>
                                    <div class="text-sm text-gray-700 font-medium">${q.student_name}</div>
                                    <div class="text-xs text-gray-600">${services}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-red-600 font-medium">
                                        <i class="far fa-clock mr-1"></i>
                                        ${waitTime} min
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center text-xs ${statusClass}">
                                    <i class="fas ${isExpired || isNearExpiry ? 'fa-exclamation-triangle' : 'fa-times-circle'} mr-1"></i>
                                    ${statusMessage}
                                </div>
                                <button onclick="resumeQueue(${q.id})" class="bg-blue-900 text-white px-3 py-1 rounded text-xs font-medium hover:bg-blue-800 flex items-center">
                                    <i class="fas fa-play mr-1"></i>
                                    Resume
                                </button>
                            </div>
                        </div>
                        `;
                    }).join('');
                }
            }
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
        
        // Helper function to convert service code to display name
        function getServiceDisplayName(serviceCode) {
            const serviceMap = {
                'good-moral': 'Good Moral Certificate',
                'insurance-payment': 'Insurance Payment',
                'approval-letter': 'Approval Letter',
                'temporary-gate-pass': 'Temporary Gate Pass',
                'certificate-request': 'Certificate Request',
                'transcript-of-records': 'Transcript of Records'
            };
            return serviceMap[serviceCode] || serviceCode;
        }
        
        // Helper function to get required documents based on service
        function getRequiredDocuments(service) {
            const documentMap = {
                'Good Moral Certificate': ['Valid Student ID', 'Certificate of Registration', 'Payment Receipt', '2x2 ID Picture'],
                'good-moral': ['Valid Student ID', 'Certificate of Registration', 'Payment Receipt', '2x2 ID Picture'],
                'Insurance Payment': ['Valid Student ID', 'Payment Receipt'],
                'insurance-payment': ['Valid Student ID', 'Payment Receipt'],
                'Approval Letter': ['Valid Student ID', 'Certificate of Registration'],
                'approval-letter': ['Valid Student ID', 'Certificate of Registration'],
                'Temporary Gate Pass': ['Valid Student ID', 'ID Picture'],
                'temporary-gate-pass': ['Valid Student ID', 'ID Picture'],
                'Certificate Request': ['Valid Student ID', 'Certificate of Registration', 'Payment Receipt'],
                'certificate-request': ['Valid Student ID', 'Certificate of Registration', 'Payment Receipt'],
                'Transcript of Records': ['Valid Student ID', 'Certificate of Registration', 'Payment Receipt', 'Clearance'],
                'transcript-of-records': ['Valid Student ID', 'Certificate of Registration', 'Payment Receipt', 'Clearance'],
                'default': ['Valid Student ID', 'Certificate of Registration', 'Payment Receipt']
            };
            return documentMap[service] || documentMap['default'];
        }
        
        // Helper function to get service note
        function getServiceNote(service) {
            const noteMap = {
                'Good Moral Certificate': 'Please bring parent\'s consent form for processing',
                'good-moral': 'Please bring parent\'s consent form for processing',
                'Insurance Payment': 'Payment must be made at the cashier',
                'insurance-payment': 'Payment must be made at the cashier',
                'Approval Letter': 'Processing time is 3-5 business days',
                'approval-letter': 'Processing time is 3-5 business days',
                'Temporary Gate Pass': 'Valid for 1 day only',
                'temporary-gate-pass': 'Valid for 1 day only',
                'Certificate Request': 'Processing time is 3-5 business days',
                'certificate-request': 'Processing time is 3-5 business days',
                'Transcript of Records': 'Requires clearance from all departments',
                'transcript-of-records': 'Requires clearance from all departments',
                'default': 'Please bring required documents for processing'
            };
            return noteMap[service] || noteMap['default'];
        }
        
        // Setup event listeners
        function setupEventListeners() {
            // Add event listeners for buttons
            const completeBtn = document.querySelector('.bg-green-600');
            const stallBtn = document.querySelector('.bg-yellow-400');
            const allButtons = document.querySelectorAll('button');
            
            // Find skip button by text content
            let skipBtn = null;
            allButtons.forEach(btn => {
                if (btn.textContent.includes('SKIP QUEUE')) {
                    skipBtn = btn;
                }
            });
            
            if (completeBtn) {
                completeBtn.addEventListener('click', completeQueue);
            }
            if (stallBtn) {
                stallBtn.addEventListener('click', stallQueue);
            }
            if (skipBtn) {
                skipBtn.addEventListener('click', skipQueue);
            }
        }
        
        // Action functions
        function completeQueue() {
            const queueId = currentQueue ? currentQueue.id : null;
            
            const formData = new FormData();
            if (!queueId) {
                // If there is no current queue yet, just call the next queue
                formData.append('action', 'next');
            } else {
                formData.append('action', 'complete');
                formData.append('queue_id', queueId);
            }
            
            fetch('queue_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear saved checkbox states for completed queue
                    if (currentQueue) {
                        localStorage.removeItem(`queue_${currentQueue.id}_checkboxes`);
                    }
                    loadQueueData();
                } else if (!window.isLoggingOut) {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (!window.isLoggingOut) {
                    alert('Failed to complete queue');
                }
            });
        }
        
        function stallQueue() {
            const queueId = currentQueue ? currentQueue.id : null;
            if (!queueId) {
                // Don't show alert if logging out
                if (!window.isLoggingOut) {
                    alert('No queue selected');
                }
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'stall');
            formData.append('queue_id', queueId);
            
            fetch('queue_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadQueueData();
                } else if (!window.isLoggingOut) {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (!window.isLoggingOut) {
                    alert('Failed to stall queue');
                }
            });
        }
        
        function skipQueue() {
            const queueId = currentQueue ? currentQueue.id : null;
            if (!queueId) {
                // Don't show alert if logging out
                if (!window.isLoggingOut) {
                    alert('No queue selected');
                }
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'skip');
            formData.append('queue_id', queueId);
            
            fetch('queue_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadQueueData();
                } else if (!window.isLoggingOut) {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (!window.isLoggingOut) {
                    alert('Failed to skip queue');
                }
            });
        }
        
        // Resume a stalled or skipped queue
        function resumeQueue(queueId) {
            if (!queueId) {
                if (!window.isLoggingOut) {
                    alert('Invalid queue ID');
                }
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'resume');
            formData.append('queue_id', queueId);
            
            fetch('queue_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadQueueData();
                } else if (!window.isLoggingOut) {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (!window.isLoggingOut) {
                    alert('Failed to resume queue');
                }
            });
        }
        
        // Make functions available globally
        window.resumeQueue = resumeQueue;
        window.toggleServiceDetails = toggleServiceDetails;
        window.showAddNoteModal = showAddNoteModal;
        window.updateDocumentVerification = updateDocumentVerification;
        window.toggleAllDocuments = toggleAllDocuments;
        
        // Save checkbox states to localStorage
        function saveCheckboxStates(queueId) {
            if (!queueId) return;
            
            const checkboxStates = {};
            const allCheckboxes = document.querySelectorAll('#servicesContainer input[type="checkbox"]');
            
            allCheckboxes.forEach(checkbox => {
                if (checkbox.id) {
                    checkboxStates[checkbox.id] = checkbox.checked;
                }
            });
            
            localStorage.setItem(`queue_${queueId}_checkboxes`, JSON.stringify(checkboxStates));
        }
        
        // Restore checkbox states from localStorage
        function restoreCheckboxStates(queueId) {
            if (!queueId) return;
            
            const savedStates = localStorage.getItem(`queue_${queueId}_checkboxes`);
            if (!savedStates) return;
            
            try {
                const checkboxStates = JSON.parse(savedStates);
                
                Object.keys(checkboxStates).forEach(checkboxId => {
                    const checkbox = document.getElementById(checkboxId);
                    if (checkbox) {
                        checkbox.checked = checkboxStates[checkboxId];
                    }
                });
                
                // Update verification displays for all services
                const serviceIds = new Set();
                Object.keys(checkboxStates).forEach(id => {
                    const match = id.match(/^(service-\d+)/);
                    if (match) {
                        serviceIds.add(match[1]);
                    }
                });
                
                serviceIds.forEach(serviceId => {
                    const docCheckboxes = document.querySelectorAll(`input[id^="${serviceId}-doc-"]`);
                    if (docCheckboxes.length > 0) {
                        updateDocumentVerification(serviceId, docCheckboxes.length);
                    }
                });
            } catch (e) {
                console.error('Error restoring checkbox states:', e);
            }
        }
        
        // Update document verification count and styling
        function updateDocumentVerification(serviceId, totalDocuments) {
            const checkboxes = document.querySelectorAll(`input[id^="${serviceId}-doc-"]`);
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            
            const verificationDiv = document.getElementById(`${serviceId}-verification`);
            const countSpan = document.getElementById(`${serviceId}-count`);
            const mainCheckbox = document.getElementById(`${serviceId}-checkbox`);
            
            if (verificationDiv && countSpan) {
                countSpan.textContent = `${checkedCount} of ${totalDocuments} documents verified`;
                
                // Update main service checkbox based on document verification
                if (mainCheckbox) {
                    mainCheckbox.checked = (checkedCount === totalDocuments && totalDocuments > 0);
                }
                
                // Update styling based on verification progress
                if (checkedCount === 0) {
                    verificationDiv.className = 'mt-3 border-l-4 border-gray-400 bg-gray-50 px-3 py-2 rounded';
                    verificationDiv.querySelector('p').className = 'text-xs text-gray-600 font-medium flex items-center';
                    verificationDiv.querySelector('i').className = 'fas fa-info-circle mr-2';
                } else if (checkedCount === totalDocuments) {
                    verificationDiv.className = 'mt-3 border-l-4 border-green-500 bg-green-50 px-3 py-2 rounded';
                    verificationDiv.querySelector('p').className = 'text-xs text-green-800 font-medium flex items-center';
                    verificationDiv.querySelector('i').className = 'fas fa-check-circle mr-2';
                } else {
                    verificationDiv.className = 'mt-3 border-l-4 border-yellow-500 bg-yellow-50 px-3 py-2 rounded';
                    verificationDiv.querySelector('p').className = 'text-xs text-yellow-800 font-medium flex items-center';
                    verificationDiv.querySelector('i').className = 'fas fa-clock mr-2';
                }
            }
            
            // Save state after updating
            if (currentQueue) {
                saveCheckboxStates(currentQueue.id);
            }
        }
        
        // Toggle service details
        function toggleServiceDetails(serviceId) {
            const details = document.getElementById(serviceId + '-details');
            const arrow = document.getElementById(serviceId + '-arrow');
            
            if (details && arrow) {
                if (details.style.display === 'none') {
                    // Show details
                    details.style.display = 'block';
                    arrow.style.transform = 'rotate(180deg)';
                } else {
                    // Hide details
                    details.style.display = 'none';
                    arrow.style.transform = 'rotate(0deg)';
                }
            }
        }
        
        // Show add note modal
        function showAddNoteModal(serviceId) {
            const noteText = prompt('Enter a note for this service:');
            if (noteText && noteText.trim()) {
                addNoteToService(serviceId, noteText.trim());
            }
        }
        
        // Add note to service
        function addNoteToService(serviceId, noteText) {
            const notesContainer = document.getElementById(serviceId + '-notes-container');
            
            if (notesContainer) {
                // Remove empty state if it exists
                const emptyState = notesContainer.querySelector('.text-gray-400.italic.text-center');
                if (emptyState) {
                    emptyState.remove();
                }
                
                // Create new note element
                const noteElement = document.createElement('div');
                noteElement.className = 'bg-gray-100 border border-gray-300 rounded p-3 text-sm text-gray-700 mb-2';
                noteElement.textContent = noteText;
                
                // Add to container
                notesContainer.appendChild(noteElement);
            }
        }
        
        // Toggle all documents when service checkbox is clicked
        function toggleAllDocuments(serviceId, totalDocuments) {
            const serviceCheckbox = document.getElementById(`${serviceId}-checkbox`);
            const documentCheckboxes = document.querySelectorAll(`input[id^="${serviceId}-doc-"]`);
            
            if (!serviceCheckbox) return;
            
            const isChecked = serviceCheckbox.checked;
            
            // Update all document checkboxes
            documentCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            
            // Update verification display
            updateDocumentVerification(serviceId, totalDocuments);
        }
        
        // Update service checkbox when individual documents are checked
        function updateServiceCheckbox(serviceId) {
            const documentCheckboxes = document.querySelectorAll(`#${serviceId}-details input[type="checkbox"]`);
            const serviceCheckbox = document.querySelector(`input[onclick*="${serviceId}"]`);
            
            const checkedCount = Array.from(documentCheckboxes).filter(cb => cb.checked).length;
            const totalCount = documentCheckboxes.length;
            
            // Update service checkbox based on document status
            if (checkedCount === totalCount) {
                serviceCheckbox.checked = true;
                serviceCheckbox.classList.remove('text-gray-400');
                serviceCheckbox.classList.add('text-green-600');
            } else {
                serviceCheckbox.checked = false;
                serviceCheckbox.classList.remove('text-green-600');
                serviceCheckbox.classList.add('text-gray-400');
            }
            
            // Update verification status
            updateVerificationStatus(serviceId);
        }
        
        // Update verification status display
        function updateVerificationStatus(serviceId) {
            const documentCheckboxes = document.querySelectorAll(`#${serviceId}-details input[type="checkbox"]`);
            const checkedCount = Array.from(documentCheckboxes).filter(cb => cb.checked).length;
            const totalCount = documentCheckboxes.length;
            
            // Find the verification status element
            const statusElement = document.querySelector(`#${serviceId}-details .inline-flex.items-center`);
            if (statusElement) {
                if (checkedCount === totalCount) {
                    statusElement.className = 'inline-flex items-center px-3 py-2 rounded-full text-sm font-medium bg-green-100 text-green-800';
                    statusElement.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + checkedCount + ' of ' + totalCount + ' documents verified';
                } else if (checkedCount > 0) {
                    statusElement.className = 'inline-flex items-center px-3 py-2 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800';
                    statusElement.innerHTML = '<i class="fas fa-clock mr-2"></i>' + checkedCount + ' of ' + totalCount + ' documents verified';
                } else {
                    statusElement.className = 'inline-flex items-center px-3 py-2 rounded-full text-sm font-medium bg-red-100 text-red-800';
                    statusElement.innerHTML = '<i class="fas fa-times-circle mr-2"></i>' + checkedCount + ' of ' + totalCount + ' documents verified';
                }
            }
        }
        
        // Add special note as a card in Service Notes section
        function addSpecialNote(serviceId) {
            const input = document.getElementById(serviceId + '-specialInput');
            const container = document.getElementById(serviceId + '-serviceNotes');
            
            if (input.value.trim() === '') {
                alert('Please enter a special note before adding.');
                return;
            }
            
            // Create special note card
            const noteCard = document.createElement('div');
            noteCard.className = 'bg-gray-100 border border-gray-200 rounded-lg p-3 flex items-start justify-between';
            noteCard.innerHTML = `
                <p class="text-gray-800 text-sm">${input.value}</p>
                <button onclick="removeServiceNote(this)" class="text-gray-500 hover:text-gray-700 ml-2">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            // Add to container
            container.appendChild(noteCard);
            
            // Clear input
            input.value = '';
        }
        
        // Remove service note card (works for both regular and special notes)
        function removeServiceNote(button) {
            button.parentElement.remove();
        }
        
        // Toggle queue section (Active, Stalled, Skipped)
        function toggleQueueSection(sectionId) {
            const content = document.getElementById(sectionId + '-content');
            const arrow = document.getElementById(sectionId + '-arrow');
            
            if (content.classList.contains('hidden')) {
                // Show content
                content.classList.remove('hidden');
                arrow.style.transform = 'rotate(180deg)';
            } else {
                // Hide content
                content.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
            }
        }
        
        // Auto-refresh queue data every 30 seconds
        setInterval(loadQueueData, 30000);
        
        // Keep-alive function to update LastActivity
        let activityInterval = null;
        function updateActivity() {
            fetch('../keepalive.php')
                .then(response => {
                    // If unauthorized (401), stop the interval but DO NOT redirect away from this page
                    if (response.status === 401) {
                        console.log('User not authenticated, stopping activity updates but staying on page');
                        if (activityInterval) {
                            clearInterval(activityInterval);
                            activityInterval = null;
                        }
                        return null;
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        console.log('Activity updated');
                    } else if (data && !data.success) {
                        // If keepalive fails, stop the interval
                        if (activityInterval) {
                            clearInterval(activityInterval);
                            activityInterval = null;
                        }
                    }
                })
                .catch(error => {
                    console.error('Activity update failed:', error);
                    // On error, stop the interval
                    if (activityInterval) {
                        clearInterval(activityInterval);
                        activityInterval = null;
                    }
                });
        }
        
        // Update activity on page load
        updateActivity();
        
        // Update activity every 2 minutes (120000 ms) while user is on the page
        activityInterval = setInterval(updateActivity, 120000);
        
        // Stop interval before page unload
        window.addEventListener('beforeunload', function() {
            if (activityInterval) {
                clearInterval(activityInterval);
                activityInterval = null;
            }
        });
    </script>
</body>
</html>