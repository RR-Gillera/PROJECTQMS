<?php
// Admin Header Component for SeQueueR
?>
<header class="bg-white border-b border-gray-200 shadow-sm">
    <div class="flex items-center justify-between py-3 px-6 md:px-10 mx-4 md:mx-8 lg:mx-12">
            <!-- Logo and Application Name Section -->
            <div class="flex items-center space-x-4">
                <!-- University Seal Logo -->
                <img alt="University of Cebu Student Affairs circular seal" class="h-12 w-12 rounded-full object-cover" src="../../../sao-nobg.png"/>
                
                <!-- Application Name -->
                <div class="leading-tight">
                    <h1 class="text-blue-900 font-bold text-xl -mb-1">SeQueueR</h1>
                    <p class="text-gray-600 text-sm">UC Student Affairs</p>
                </div>
            </div>
            
            <!-- Navigation Tabs -->
            <nav class="flex items-center space-x-1">
                <!-- Dashboard Tab -->
                <a href="Dashboard.php" class="nav-tab flex items-center space-x-2 px-4 py-2 rounded-lg transition-colors" id="dashboardTab">
                    <i class="fas fa-home nav-icon text-gray-600"></i>
                    <span class="nav-text text-gray-600 font-medium">Dashboard</span>
                </a>
                
                <!-- Queue Management Tab -->
                <a href="Queue.php" class="nav-tab flex items-center space-x-2 px-4 py-2 rounded-lg transition-colors" id="queueTab">
                    <i class="fas fa-clipboard-list nav-icon text-gray-600"></i>
                    <span class="nav-text text-gray-600 font-medium">Queue Management</span>
                </a>
                
                <!-- Queue History Tab -->
                <a href="History.php" class="nav-tab flex items-center space-x-2 px-4 py-2 rounded-lg transition-colors" id="historyTab">
                    <i class="fas fa-history nav-icon text-gray-600"></i>
                    <span class="nav-text text-gray-600 font-medium">Queue History</span>
                </a>
                
                <!-- Account Management Tab -->
                <a href="User.php" class="nav-tab flex items-center space-x-2 px-4 py-2 rounded-lg transition-colors" id="userTab">
                    <i class="fas fa-user-plus nav-icon text-gray-600"></i>
                    <span class="nav-text text-gray-600 font-medium">Account Management</span>
                </a>
            </nav>
            
            <!-- User Profile Section -->
            <div class="flex items-center space-x-3">
                <!-- User Profile Picture -->
                <div class="relative">
                    <img id="userProfileImage" 
                         src="https://placehold.co/40x40/4f46e5/ffffff?text=U" 
                         alt="User Profile" 
                         class="w-10 h-10 rounded-full border-2 border-gray-200 cursor-pointer hover:border-blue-500 transition-colors"
                         onclick="toggleUserDropdown()">
                </div>
                
                <!-- Dropdown Arrow -->
                <button id="userDropdownBtn" class="text-gray-400 hover:text-gray-600 transition-colors" onclick="toggleUserDropdown()">
                    <i class="fas fa-chevron-down text-sm transition-transform" id="profileArrow"></i>
                </button>
            </div>
    </div>
</header>

<!-- User Dropdown Menu (Hidden by default) -->
<div id="userDropdown" class="absolute right-6 top-16 w-64 bg-white rounded-lg shadow-lg border border-gray-200 hidden z-50">
    <div class="p-3">
        <button onclick="window.location.href='Settings.php'" class="w-full flex items-center space-x-3 px-4 py-3 text-left text-gray-700 bg-blue-100 hover:bg-blue-200 transition rounded-lg mb-2">
            <i class="fas fa-cog text-xl"></i>
            <span class="font-medium text-lg">Settings</span>
        </button>
        <button onclick="showLogoutModal()" class="w-full flex items-center space-x-3 px-4 py-3 text-left text-gray-700 hover:bg-gray-100 transition rounded-lg">
            <i class="fas fa-sign-out-alt text-xl"></i>
            <span class="font-medium text-lg">Logout</span>
        </button>
    </div>
</div>

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
    // Backend-ready JavaScript for Admin Header
    let currentUser = null;
    
    // Initialize the header
    document.addEventListener('DOMContentLoaded', function() {
        loadUserProfile();
        setupEventListeners();
        initPageTransition();
        // Use requestAnimationFrame to ensure DOM is fully ready
        requestAnimationFrame(updateActiveTab);
    });
    
    // Load user profile from backend
    function loadUserProfile() {
        // TODO: Replace with actual API call
        fetch('/api/admin/user/profile')
            .then(response => response.json())
            .then(data => {
                currentUser = data;
                updateUserDisplay();
            })
            .catch(error => {
                console.log('No backend connection yet - using default user');
                // Default user data when no backend
                currentUser = {
                    name: 'Admin User',
                    role: 'Administrator',
                    profileImage: 'https://placehold.co/40x40/4f46e5/ffffff?text=U'
                };
                updateUserDisplay();
            });
    }
    
    // Update user display elements
    function updateUserDisplay() {
        if (currentUser) {
            // Only update elements that exist
            const userNameElement = document.getElementById('userName');
            const userRoleElement = document.getElementById('userRole');
            const userProfileImageElement = document.getElementById('userProfileImage');
            
            if (userNameElement) userNameElement.textContent = currentUser.name || 'Admin User';
            if (userRoleElement) userRoleElement.textContent = currentUser.role || 'Administrator';
            if (userProfileImageElement) userProfileImageElement.src = currentUser.profileImage || 'https://placehold.co/40x40/4f46e5/ffffff?text=U';
        }
    }
    
    // Setup event listeners
    function setupEventListeners() {
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const dropdownBtn = document.getElementById('userDropdownBtn');
            const profileImage = document.getElementById('userProfileImage');
            
            // Check if click is outside the dropdown, button, and profile image
            if (!dropdown.contains(event.target) && 
                !dropdownBtn.contains(event.target) && 
                event.target !== profileImage &&
                !event.target.closest('#userDropdownBtn') &&
                !event.target.closest('#userProfileImage')) {
                closeUserDropdown();
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
    
    // Update active tab based on current page
    function updateActiveTab() {
        const currentPage = window.location.pathname.split('/').pop();
        
        // Remove active class from all tabs
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Add active class to current page tab
        const activeTab = document.querySelector(`a[href="${currentPage}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
        }
    }
    
    // Toggle user dropdown
    function toggleUserDropdown(event) {
        if (event) {
            event.stopPropagation();
        }
        
        const dropdown = document.getElementById('userDropdown');
        const arrow = document.getElementById('profileArrow');
        
        if (dropdown.classList.contains('hidden')) {
            openUserDropdown();
            arrow.style.transform = 'rotate(180deg)';
        } else {
            closeUserDropdown();
            arrow.style.transform = 'rotate(0deg)';
        }
    }
    
    // Make function globally available
    window.toggleUserDropdown = toggleUserDropdown;
    
    // Open user dropdown
    function openUserDropdown() {
        document.getElementById('userDropdown').classList.remove('hidden');
    }
    
    // Close user dropdown
    function closeUserDropdown() {
        document.getElementById('userDropdown').classList.add('hidden');
        document.getElementById('profileArrow').style.transform = 'rotate(0deg)';
    }
    
    // Show logout modal
    function showLogoutModal() {
        document.getElementById('logoutModal').classList.remove('hidden');
        // Close dropdown
        document.getElementById('userDropdown').classList.add('hidden');
        document.getElementById('profileArrow').style.transform = 'rotate(0deg)';
    }
    
    // Close logout modal
    function closeLogoutModal() {
        document.getElementById('logoutModal').classList.add('hidden');
    }
    
    // Confirm logout
    function confirmLogout() {
        // Stop activity interval immediately before logout to prevent any more updates
        if (activityInterval) {
            clearInterval(activityInterval);
            activityInterval = null;
            console.log('Stopped activity updates before logout');
        }
        // Redirect to logout handler which clears session
        window.location.href = '../Logout.php';
    }
    
    // Make functions globally available
    window.showLogoutModal = showLogoutModal;
    window.closeLogoutModal = closeLogoutModal;
    window.confirmLogout = confirmLogout;
    
    // Auto-refresh user profile every 5 minutes
    setInterval(loadUserProfile, 300000);
    
    // Keep-alive function to update LastActivity
    let activityInterval = null;
    function updateActivity() {
        fetch('../keepalive.php')
            .then(response => {
                // If unauthorized (401), user is logged out - stop the interval immediately
                if (response.status === 401) {
                    console.log('User logged out, stopping activity updates');
                    if (activityInterval) {
                        clearInterval(activityInterval);
                        activityInterval = null;
                    }
                    // Redirect to login if on a protected page
                    if (window.location.pathname.includes('Admin/') || 
                        window.location.pathname.includes('Working/')) {
                        window.location.href = '../Signin.php';
                    }
                    // Return early, don't try to parse JSON
                    throw new Error('Unauthorized - session expired');
                }
                if (!response.ok) {
                    throw new Error('Activity update failed: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data && data.success) {
                    console.log('Activity updated');
                } else if (data && !data.success) {
                    // If keepalive fails, stop the interval
                    console.log('Activity update failed, stopping interval');
                    if (activityInterval) {
                        clearInterval(activityInterval);
                        activityInterval = null;
                    }
                }
            })
            .catch(error => {
                // Only log non-401 errors (401 is expected on logout)
                if (!error.message.includes('Unauthorized')) {
                    console.error('Activity update failed:', error);
                }
                // On error, stop the interval to prevent repeated failed requests
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