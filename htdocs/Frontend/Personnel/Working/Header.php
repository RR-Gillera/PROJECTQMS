<?php
// Show worker initials in the header avatar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$workerInitials = 'WS';
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $name = trim((string)($_SESSION['user']['fullName'] ?? ''));
    if ($name === '' && isset($_SESSION['user']['studentId'])) {
        $name = (string)$_SESSION['user']['studentId'];
    }
    if ($name !== '') {
        // Expected format: "Lastname, Firstname Middlename."
        $lastName = '';
        $givenNames = [];

        if (strpos($name, ',') !== false) {
            [$lastPart, $rest] = array_map('trim', explode(',', $name, 2));
            $lastName = $lastPart;
            // Split remaining part into given-name pieces, stripping trailing dots
            $givenParts = preg_split('/\s+/', $rest);
            foreach ($givenParts as $p) {
                $clean = trim($p, " \t\n\r\0\x0B.");
                if ($clean !== '') {
                    $givenNames[] = $clean;
                }
            }
        } else {
            // Fallback: no comma, treat as words: first = given, last = last word
            $parts = preg_split('/\s+/', $name);
            if (count($parts) > 0) {
                $givenNames[] = $parts[1] ?? $parts[0];
                $lastName = $parts[count($parts) - 1];
            }
        }

        // Build initials:
        // - If at least two given names: use first letters of first two (e.g. "Russell Ray" -> "RR")
        // - If only one given name: first letter of given + first letter of last name (e.g. "Gillera, Paul" -> "PG")
        $firstInitial = '';
        $secondInitial = '';

        if (count($givenNames) >= 2) {
            $firstInitial = mb_substr($givenNames[0], 0, 1, 'UTF-8');
            $secondInitial = mb_substr($givenNames[1], 0, 1, 'UTF-8');
        } elseif (count($givenNames) === 1) {
            $firstInitial = mb_substr($givenNames[0], 0, 1, 'UTF-8');
            if ($lastName !== '') {
                $secondInitial = mb_substr($lastName, 0, 1, 'UTF-8');
            }
        }

        // Fallback if something went wrong: use first and last word initials
        if ($firstInitial === '' || $secondInitial === '') {
            $parts = preg_split('/\s+/', $name);
            if (count($parts) > 0) {
                $firstInitial = mb_substr($parts[0], 0, 1, 'UTF-8');
                $secondInitial = mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8');
            }
        }

        if ($firstInitial !== '' && $secondInitial !== '') {
            $workerInitials = strtoupper($firstInitial . $secondInitial);
        }
    }
}
?>

<header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <div class="flex items-center justify-between py-3 px-6 md:px-10 mx-4 md:mx-8 lg:mx-12">
        <!-- Left Section - Branding -->
        <div class="flex items-center space-x-4">
            <img alt="University of Cebu Student Affairs circular seal" class="h-12 w-12 rounded-full object-cover" src="/Frontend/Assests/SAO.png"/>
            <div class="leading-tight">
                <h1 class="text-blue-900 font-bold text-xl -mb-1">SeQueueR</h1>
                <p class="text-gray-600 text-sm">UC Student Affairs</p>
            </div>
        </div>

        <!-- Center Section - Navigation -->
        <div class="flex items-center space-x-8">
            <!-- Queue/Transaction -->
            <a href="Queue.php" class="nav-tab active flex items-center space-x-2 px-4 py-2 rounded-md transition-colors" id="queueTab">
                <i class="fas fa-clipboard-list nav-icon"></i>
                <span class="nav-text font-medium">Queue Management</span>
            </a>
            
            <!-- History -->
            <a href="History.php" class="nav-tab flex items-center space-x-2 px-4 py-2 rounded-md transition-colors" id="historyTab">
                <i class="fas fa-history nav-icon"></i>
                <span class="nav-text font-medium">Queue History</span>
            </a>
        </div>

        <!-- Right Section - User Profile -->
        <div class="flex items-center space-x-3 relative">
            <!-- User Profile Initials -->
            <div class="relative">
                <button id="userProfileImage"
                        class="w-10 h-10 rounded-full border-2 border-gray-200 bg-blue-900 text-white font-semibold flex items-center justify-center cursor-pointer hover:border-blue-500 transition-colors select-none"
                        onclick="toggleProfileDropdown(event)">
                    <?php echo htmlspecialchars($workerInitials, ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </div>
            
            <!-- Dropdown Arrow -->
            <button id="userDropdownBtn" class="text-gray-400 hover:text-gray-600 transition-colors" onclick="toggleProfileDropdown()">
                <i class="fas fa-chevron-down text-sm transition-transform" id="profileArrow"></i>
            </button>

            <!-- User Dropdown Menu (Hidden by default, anchored to header) -->
            <div id="profileDropdown" class="absolute right-0 top-12 w-64 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-50">
                <div class="p-3">
                    <button onclick="showLogoutModal()" class="w-full flex items-center space-x-3 px-4 py-3 text-left text-gray-700 hover:bg-gray-100 transition rounded-lg">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                        <span class="font-medium text-lg">Logout</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <div class="flex items-center justify-center mb-4">
            <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
            </div>
        </div>
        <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Logout Confirmation</h3>
        <p class="text-gray-600 text-center mb-6">Are you sure you want to Logout?</p>
        <div class="flex justify-center gap-3">
            <button type="button" onclick="closeLogoutModal()" 
                    class="border border-blue-500 text-blue-500 rounded-md text-sm hover:bg-blue-50 transition font-medium" 
                    style="padding: 8px 24px; width: 120px; height: 36px;">
                No
            </button>
            <button type="button" onclick="confirmLogout()" 
                    class="bg-blue-900 text-white rounded-md text-sm hover:bg-blue-800 transition font-medium" 
                    style="padding: 8px 24px; width: 120px; height: 36px;">
                Yes
            </button>
        </div>
    </div>
</div>

<style>
    /* Global smooth navigation styles */
    * {
        box-sizing: border-box;
    }
    
    html {
        scroll-behavior: smooth;
    }
    
    body {
        transition: opacity 0.2s ease-in-out;
    }
    
    /* Navigation tab styles */
    .nav-tab {
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        border-bottom: 2px solid transparent;
        position: relative;
        transform: translateZ(0); /* Hardware acceleration */
        will-change: background-color, color, border-color;
    }
    
    .nav-tab:hover {
        background-color: #f3f4f6;
        transform: translateY(-1px);
    }
    
    .nav-tab.active {
        background-color: #dbeafe;
        border-bottom-color: #1e40af;
        transform: translateY(0);
    }
    
    .nav-tab.active .nav-icon,
    .nav-tab.active .nav-text {
        color: #1e40af !important;
    }
    
    .nav-tab:not(.active) .nav-icon,
    .nav-tab:not(.active) .nav-text {
        color: #6b7280;
    }
    
    /* Prevent layout shifts */
    .nav-tab i, .nav-tab span {
        display: inline-block;
        vertical-align: middle;
    }
    
    /* Loading state for navigation */
    .nav-tab.loading {
        opacity: 0.7;
        pointer-events: none;
    }
    
    /* Smooth page transitions */
    .page-transition {
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
    }
    
    .page-transition.loaded {
        opacity: 1;
        transform: translateY(0);
    }
    
    /* Loading spinner */
    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #1e40af;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<script>
    // Highlight active tab based on current page
    function updateActiveTab() {
        const currentPage = window.location.pathname.split('/').pop();
        
        // Remove active class from all tabs
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Add active class to current page tab
        let activeTab = document.querySelector(`a[href="${currentPage}"]`);
        
        // If no direct match, try different variations
        if (!activeTab) {
            // Try with different path formats
            const variations = [
                currentPage,
                currentPage.toLowerCase(),
                currentPage.toUpperCase(),
                currentPage.replace('.php', ''),
                currentPage.replace('.php', '.php')
            ];
            
            for (const variation of variations) {
                activeTab = document.querySelector(`a[href="${variation}"]`);
                if (activeTab) break;
            }
        }
        
        if (activeTab) {
            activeTab.classList.add('active');
        } else {
            // Fallback: default to Queue tab for Queue.php or empty path
            if (currentPage === 'Queue.php' || currentPage === '' || currentPage.includes('Queue')) {
                const queueTab = document.getElementById('queueTab');
                if (queueTab) {
                    queueTab.classList.add('active');
                }
            }
        }
    }
    
    // Setup event listeners
    function setupEventListeners() {
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const dropdownBtn = document.getElementById('userDropdownBtn');
            const profileImage = document.getElementById('userProfileImage');
            
            // Check if click is outside the dropdown, button, and profile image
            if (!dropdown.contains(event.target) && 
                !dropdownBtn.contains(event.target) && 
                event.target !== profileImage &&
                !event.target.closest('#userDropdownBtn') &&
                !event.target.closest('#userProfileImage')) {
                closeProfileDropdown();
            }
        });
        
        // Add smooth navigation with loading states
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                // Add loading state
                this.classList.add('loading');
                
                // Add loading spinner
                const originalContent = this.innerHTML;
                this.innerHTML = '<div class="loading-spinner"></div>';
                
                // Preload the target page
                const href = this.getAttribute('href');
                if (href) {
                    preloadPage(href);
                }
                
                // Smooth page transition
                setTimeout(() => {
                    // Restore original content
                    this.innerHTML = originalContent;
                    this.classList.remove('loading');
                }, 300);
            });
            
            // Preload on hover
            tab.addEventListener('mouseenter', function() {
                const href = this.getAttribute('href');
                if (href) {
                    preloadPage(href);
                }
            });
        });
    }
    
    // Preload page for faster navigation
    function preloadPage(url) {
        if (preloadPage.cache && preloadPage.cache[url]) {
            return; // Already preloaded
        }
        
        if (!preloadPage.cache) {
            preloadPage.cache = {};
        }
        
        // Create a hidden link to preload the page
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        document.head.appendChild(link);
        
        preloadPage.cache[url] = true;
    }
    
    // Smooth page load animation
    function initPageTransition() {
        document.body.classList.add('page-transition');
        
        // Trigger loaded state after a short delay
        requestAnimationFrame(() => {
            document.body.classList.add('loaded');
        });
    }
    
    // Initialize the header
    document.addEventListener('DOMContentLoaded', function() {
        setupEventListeners();
        initPageTransition();
        // Use requestAnimationFrame to ensure DOM is fully ready
        requestAnimationFrame(updateActiveTab);
    });
    
    // Toggle profile dropdown
    function toggleProfileDropdown(event) {
        if (event) {
            event.stopPropagation();
        }
        
        const dropdown = document.getElementById('profileDropdown');
        const arrow = document.getElementById('profileArrow');
        
        if (dropdown.classList.contains('hidden')) {
            openProfileDropdown();
            arrow.style.transform = 'rotate(180deg)';
        } else {
            closeProfileDropdown();
            arrow.style.transform = 'rotate(0deg)';
        }
    }
    
    // Make function globally available
    window.toggleProfileDropdown = toggleProfileDropdown;
    
    // Open profile dropdown
    function openProfileDropdown() {
        document.getElementById('profileDropdown').classList.remove('hidden');
    }
    
    // Close profile dropdown
    function closeProfileDropdown() {
        document.getElementById('profileDropdown').classList.add('hidden');
        document.getElementById('profileArrow').style.transform = 'rotate(0deg)';
    }
    
    // Show logout modal
    function showLogoutModal() {
        document.getElementById('logoutModal').classList.remove('hidden');
        // Close dropdown
        document.getElementById('profileDropdown').classList.add('hidden');
        document.getElementById('profileArrow').style.transform = 'rotate(0deg)';
    }
    
    // Close logout modal
    function closeLogoutModal() {
        document.getElementById('logoutModal').classList.add('hidden');
    }
    
    // Confirm logout
    function confirmLogout() {
        // Set a flag to prevent any alerts during logout
        window.isLoggingOut = true;
        
        // Redirect to logout handler which will release counter assignment
        window.location.href = '../Logout.php';
    }
    
    // Make functions globally available
    window.showLogoutModal = showLogoutModal;
    window.closeLogoutModal = closeLogoutModal;
    window.confirmLogout = confirmLogout;
</script>
