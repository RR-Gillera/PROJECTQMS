<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - SeQueueR</title>
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
    <!-- Include Working Header -->
    <?php include 'Header.php'; ?>
    
    <!-- Main Content -->
    <main class="bg-gray-50 min-h-screen">
        <div class="py-8 px-6 md:px-10 mx-4 md:mx-8 lg:mx-12">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Transaction History</h1>
                    <p class="text-gray-600 mt-2">View and manage your past queue transactions</p>
                </div>
                <!-- Export Dropdown -->
                <div class="mt-4 sm:mt-0 relative" id="exportDropdown">
                    <button id="exportBtn" class="bg-blue-900 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded-lg flex items-center space-x-2" onclick="toggleExportDropdown(event)">
                        <i class="fas fa-download"></i>
                        <span>Export</span>
                        <i class="fas fa-chevron-down ml-1"></i>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div id="exportMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-10">
                        <div class="py-2">
                            <div class="px-4 py-2 text-sm fon   t-medium text-gray-700 border-b border-gray-100">
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
                                <button onclick="exportToWord()" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-3">
                                    <div class="w-6 h-6 bg-blue-600 rounded flex items-center justify-center">
                                        <span class="text-white text-xs font-bold">W</span>
                                    </div>
                                    <span>Word</span>
                                </button>
                            </div>
                        </div>
                    </div>
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
                            <input type="text" id="searchInput" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Search by queue number, student name, or...">
                        </div>
                    </div>
                    
                    <!-- Filter Dropdowns -->
                    <div class="flex flex-col sm:flex-row sm:space-x-4 space-y-4 sm:space-y-0">
                        <div class="relative">
                            <select id="dateRangeFilter" class="appearance-none bg-white border border-gray-300 rounded-md px-3 py-2 pr-8 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Date Range</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="custom">Custom Range</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <i class="fas fa-calendar text-gray-400"></i>
                            </div>
                        </div>
                        
                        <div class="relative">
                            <select id="statusFilter" class="appearance-none bg-white border border-gray-300 rounded-md px-3 py-2 pr-8 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Statuses</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="stalled">Stalled</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <i class="fas fa-circle text-gray-400"></i>
                            </div>
                        </div>
                        
                        <div class="relative">
                            <select id="serviceFilter" class="appearance-none bg-white border border-gray-300 rounded-md px-3 py-2 pr-8 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Services</option>
                                <option value="good_moral">Good Moral Certificate</option>
                                <option value="transcript">Transcript Request</option>
                                <option value="certificate">Certificate Request</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <i class="fas fa-file-alt text-gray-400"></i>
                            </div>
                        </div>
                        
                        <button id="clearFiltersBtn" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-md flex items-center space-x-2">
                            <i class="fas fa-sync-alt"></i>
                            <span>Clear Filters</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Transaction Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('queueNumber')">
                                    <div class="flex items-center space-x-1">
                                        <span>Queue Number</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('studentName')">
                                    <div class="flex items-center space-x-1">
                                        <span>Student Name</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('serviceType')">
                                    <div class="flex items-center space-x-1">
                                        <span>Service Type</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('status')">
                                    <div class="flex items-center space-x-1">
                                        <span>Status</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('dateTime')">
                                    <div class="flex items-center space-x-1">
                                        <span>Date & Time</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="sortTable('waitTime')">
                                    <div class="flex items-center space-x-1">
                                        <span>Wait Time</span>
                                        <i class="fas fa-sort text-gray-400"></i>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="transactionTableBody" class="bg-white divide-y divide-gray-200">
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
    
    <!-- Include Footer -->
    <?php include '../../Footer.php'; ?>
    
    <script>
        // Backend-ready JavaScript for Transaction History
        let transactions = [];
        let currentPage = 1;
        let totalPages = 1;
        let itemsPerPage = 10;
        let currentSort = { column: '', direction: 'asc' };
        let currentFilters = {
            search: '',
            dateRange: '',
            status: '',
            service: ''
        };
        
        // Initialize the interface
        document.addEventListener('DOMContentLoaded', function() {
            loadTransactionHistory();
            setupEventListeners();
        });
        
        // Load transaction history from backend
        function loadTransactionHistory() {
            const params = new URLSearchParams({
                page: currentPage,
                limit: itemsPerPage,
                search: currentFilters.search || '',
                date_range: currentFilters.dateRange || '',
                status: currentFilters.status || '',
                service: currentFilters.service || ''
            });
            
            fetch(`history_api.php?${params}`)
                .then(response => {
                    if (response.status === 401) {
                        window.location.href = '../Signin.php';
                        return null;
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                    transactions = data.transactions || [];
                    totalPages = data.totalPages || 1;
                    updateTransactionTable();
                    updatePagination();
                    updateExportButton();
                    } else {
                        transactions = [];
                        totalPages = 1;
                        updateTransactionTable();
                        updatePagination();
                        updateExportButton();
                    }
                })
                .catch(error => {
                    console.error('Error loading transaction history:', error);
                    transactions = [];
                    totalPages = 1;
                    updateTransactionTable();
                    updatePagination();
                    updateExportButton();
                });
        }
        
        // Update transaction table
        function updateTransactionTable() {
            const tbody = document.getElementById('transactionTableBody');
            
            if (transactions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-history text-gray-300 text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No transactions found</h3>
                                <p class="text-gray-500">No transaction history available at the moment.</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = transactions.map(transaction => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            ${transaction.priority === 'priority' ? '<i class="fas fa-star text-yellow-500 mr-2"></i>' : ''}
                            <span class="text-sm font-medium text-blue-600">${transaction.queueNumber}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div>
                            <div class="text-sm font-medium text-gray-900">${transaction.studentName}</div>
                            <div class="text-sm text-gray-500">${transaction.studentId}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <i class="fas fa-certificate text-yellow-500 mr-2"></i>
                            <span class="text-sm text-gray-900">${transaction.serviceType}</span>
                            ${transaction.additionalServices > 0 ? `<span class="text-xs text-blue-600 ml-1">+${transaction.additionalServices}</span>` : ''}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                            transaction.status === 'completed' ? 'bg-green-100 text-green-800' :
                            transaction.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                            'bg-yellow-100 text-yellow-800'
                        }">
                            ${transaction.status.charAt(0).toUpperCase() + transaction.status.slice(1)}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div>
                            <div>${transaction.dateTime}</div>
                            <div class="text-gray-500">${transaction.time}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${transaction.waitTime}
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
            const endItem = Math.min(currentPage * itemsPerPage, transactions.length);
            
            showingFrom.textContent = transactions.length > 0 ? startItem : 0;
            showingTo.textContent = endItem;
            totalResults.textContent = transactions.length;
            
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
                        i === currentPage ? 'text-blue-600 bg-blue-50 border-blue-300' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50'
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
            if (transactions.length === 0) {
                exportBtn.classList.add('opacity-50', 'cursor-not-allowed');
                exportBtn.classList.remove('hover:bg-blue-800');
            } else {
                exportBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                exportBtn.classList.add('hover:bg-blue-800');
            }
        }
        
        // Setup event listeners
        function setupEventListeners() {
            document.getElementById('searchInput').addEventListener('input', debounce(handleSearch, 300));
            document.getElementById('dateRangeFilter').addEventListener('change', handleFilterChange);
            document.getElementById('statusFilter').addEventListener('change', handleFilterChange);
            document.getElementById('serviceFilter').addEventListener('change', handleFilterChange);
            document.getElementById('clearFiltersBtn').addEventListener('click', clearFilters);
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                const dropdown = document.getElementById('exportDropdown');
                if (!dropdown.contains(event.target)) {
                    closeExportDropdown();
                }
            });
        }
        
        // Handle search
        function handleSearch(event) {
            currentFilters.search = event.target.value;
            currentPage = 1;
            loadTransactionHistory();
        }
        
        // Handle filter changes
        function handleFilterChange(event) {
            const filterType = event.target.id.replace('Filter', '');
            currentFilters[filterType] = event.target.value;
            currentPage = 1;
            loadTransactionHistory();
        }
        
        // Clear all filters
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('dateRangeFilter').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('serviceFilter').value = '';
            
            currentFilters = {
                search: '',
                dateRange: '',
                status: '',
                service: ''
            };
            
            currentPage = 1;
            loadTransactionHistory();
        }
        
        // Change page
        function changePage(page) {
            currentPage = page;
            loadTransactionHistory();
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
            loadTransactionHistory();
        }
        
        // Toggle export dropdown
        function toggleExportDropdown(event) {
            event.preventDefault();
            event.stopPropagation();
            
            // Don't open dropdown if there are no transactions
            if (transactions.length === 0) {
                console.log('No transactions to export');
                return;
            }
            
            console.log('Export button clicked'); // Debug log
            
            const menu = document.getElementById('exportMenu');
            if (menu.classList.contains('hidden')) {
                openExportDropdown();
            } else {
                closeExportDropdown();
            }
        }
        
        // Open export dropdown
        function openExportDropdown() {
            const menu = document.getElementById('exportMenu');
            menu.classList.remove('hidden');
            console.log('Dropdown opened'); // Debug log
        }
        
        // Close export dropdown
        function closeExportDropdown() {
            const menu = document.getElementById('exportMenu');
            menu.classList.add('hidden');
            console.log('Dropdown closed'); // Debug log
        }
        
        // Export functions (no backend yet)
        function exportToPDF() {
            if (transactions.length === 0) {
                console.log('No transactions to export to PDF');
                return;
            }
            closeExportDropdown();
            console.log('Export to PDF - Backend not implemented yet');
        }
        
        function exportToExcel() {
            if (transactions.length === 0) {
                console.log('No transactions to export to Excel');
                return;
            }
            closeExportDropdown();
            console.log('Export to Excel - Backend not implemented yet');
        }
        
        function exportToCSV() {
            if (transactions.length === 0) {
                console.log('No transactions to export to CSV');
                return;
            }
            closeExportDropdown();
            console.log('Export to CSV - Backend not implemented yet');
        }
        
        function exportToWord() {
            if (transactions.length === 0) {
                console.log('No transactions to export to Word');
                return;
            }
            closeExportDropdown();
            console.log('Export to Word - Backend not implemented yet');
        }
        
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
        setInterval(loadTransactionHistory, 60000);
        
        // Keep-alive function to update LastActivity
        let activityInterval = null;
        function updateActivity() {
            fetch('../keepalive.php')
                .then(response => {
                    // If unauthorized (401), user is logged out - stop the interval
                    if (response.status === 401) {
                        console.log('User logged out, stopping activity updates');
                        if (activityInterval) {
                            clearInterval(activityInterval);
                            activityInterval = null;
                        }
                        window.location.href = '../Signin.php';
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