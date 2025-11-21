<?php
session_start();
require_once __DIR__ . '/../../Student/db_config.php';

// Get current settings for display
$conn = getDBConnection();

// Default settings
$settings = [
    'maxQueueCapacity' => 100,
    'skipTimeout' => 1,
    'skipTimeoutUnit' => 'hours',
    'sessionTimeout' => 30
];

// Load from database
try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - SeQueueR Admin</title>
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
    <?php include 'Header.php'; ?>
    
    <main class="min-h-screen">
        <div class="py-8 px-6 md:px-10 mx-4 md:mx-8 lg:mx-12">
            
            <!-- Success/Error Messages -->
            <div id="alertContainer"></div>
            
            <!-- Settings Container -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <form id="settingsForm">
                    <!-- Queue Limits Section -->
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Queue Limits</h2>
                        <p class="text-gray-600 mb-6">
                            Set the maximum number of active queue entries across all services. This helps manage workload and prevents system overload.
                        </p>
                        
                        <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                            <label class="block text-sm font-semibold text-gray-900 mb-2">
                                Maximum Queue Capacity
                            </label>
                            <input type="number" 
                                   id="maxQueueCapacity" 
                                   name="maxQueueCapacity"
                                   class="w-48 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   value="<?php echo htmlspecialchars($settings['maxQueueCapacity']); ?>"
                                   min="1"
                                   max="500"
                                   required>
                            <div class="flex items-start mt-3 text-sm text-gray-600">
                                <i class="fas fa-info-circle mt-0.5 mr-2"></i>
                                <span>Total number of students that can queue simultaneously across all services. Current active queues will be shown below after saving.</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Timeout Settings Section -->
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Timeout Settings</h2>
                        <p class="text-gray-600 mb-6">
                            Configure automatic timeout durations for different queue states to maintain system efficiency.
                        </p>
                        
                        <div class="bg-gray-50 rounded-lg p-6 border border-gray-200 space-y-6">
                            <!-- Auto-Cancel Skipped Queues -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-2">
                                    Auto-Cancel Skipped Queues After
                                </label>
                                <div class="flex items-center space-x-3">
                                    <input type="number" 
                                           id="skipTimeout" 
                                           name="skipTimeout"
                                           class="w-24 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           value="<?php echo htmlspecialchars($settings['skipTimeout']); ?>"
                                           min="1"
                                           max="24"
                                           required>
                                    <select id="skipTimeoutUnit" 
                                            name="skipTimeoutUnit"
                                            class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none bg-white pr-10"
                                            style="background-image: url('data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27currentColor%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3e%3cpolyline points=%276 9 12 15 18 9%27%3e%3c/polyline%3e%3c/svg%3e'); background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 1.25rem;">
                                        <option value="minutes" <?php echo $settings['skipTimeoutUnit'] === 'minutes' ? 'selected' : ''; ?>>Minute(s)</option>
                                        <option value="hours" <?php echo $settings['skipTimeoutUnit'] === 'hours' ? 'selected' : ''; ?>>Hour(s)</option>
                                    </select>
                                </div>
                                <div class="flex items-start mt-3 text-sm text-gray-600">
                                    <i class="fas fa-info-circle mt-0.5 mr-2"></i>
                                    <span>Skipped queues that remain unattended will be automatically cancelled after this duration. (SRS Requirement 3.1.5.4)</span>
                                </div>
                            </div>
                            
                            <!-- Session Inactivity Timeout -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-2">
                                    Session Inactivity Timeout
                                </label>
                                <div class="flex items-center space-x-3">
                                    <input type="number" 
                                           id="sessionTimeout" 
                                           name="sessionTimeout"
                                           class="w-24 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           value="<?php echo htmlspecialchars($settings['sessionTimeout']); ?>"
                                           min="5"
                                           max="120"
                                           required>
                                    <span class="text-gray-700">minutes</span>
                                </div>
                                <div class="flex items-start mt-3 text-sm text-gray-600">
                                    <i class="fas fa-info-circle mt-0.5 mr-2"></i>
                                    <span>Working Scholars will be automatically logged out after this period of inactivity for security purposes.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                        <button type="button" 
                                onclick="resetToDefaults()" 
                                class="flex items-center space-x-2 px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                            <i class="fas fa-redo-alt"></i>
                            <span>Reset to Default Values</span>
                        </button>
                        
                        <div class="flex items-center space-x-3">
                            <button type="button" 
                                    onclick="cancelChanges()" 
                                    class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="flex items-center space-x-2 px-6 py-2.5 bg-blue-900 text-white rounded-lg hover:bg-blue-800 transition font-medium">
                                <i class="fas fa-check"></i>
                                <span>Save Changes</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <?php include '../../Footer.php'; ?>
    
    <script>
        let originalSettings = <?php echo json_encode($settings); ?>;
        
        const defaultSettings = {
            maxQueueCapacity: 100,
            skipTimeout: 1,
            skipTimeoutUnit: 'hours',
            sessionTimeout: 30
        };
        
        // Handle form submission
        document.getElementById('settingsForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = {
                maxQueueCapacity: parseInt(document.getElementById('maxQueueCapacity').value),
                skipTimeout: parseInt(document.getElementById('skipTimeout').value),
                skipTimeoutUnit: document.getElementById('skipTimeoutUnit').value,
                sessionTimeout: parseInt(document.getElementById('sessionTimeout').value)
            };
            
            // Validate
            if (formData.maxQueueCapacity < 1 || formData.maxQueueCapacity > 500) {
                showAlert('Maximum Queue Capacity must be between 1 and 500.', 'error');
                return;
            }
            
            if (formData.skipTimeout < 1 || formData.skipTimeout > 24) {
                showAlert('Skip timeout must be between 1 and 24.', 'error');
                return;
            }
            
            if (formData.sessionTimeout < 5 || formData.sessionTimeout > 120) {
                showAlert('Session timeout must be between 5 and 120 minutes.', 'error');
                return;
            }
            
            try {
                const response = await fetch('settings_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('Settings saved successfully!', 'success');
                    originalSettings = { ...formData };
                    
                    // Reload page after 1 second to show updated values
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showAlert(data.message || 'Failed to save settings', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('An error occurred while saving settings', 'error');
            }
        });
        
        function resetToDefaults() {
            if (confirm('Are you sure you want to reset all settings to their default values?')) {
                document.getElementById('maxQueueCapacity').value = defaultSettings.maxQueueCapacity;
                document.getElementById('skipTimeout').value = defaultSettings.skipTimeout;
                document.getElementById('skipTimeoutUnit').value = defaultSettings.skipTimeoutUnit;
                document.getElementById('sessionTimeout').value = defaultSettings.sessionTimeout;
            }
        }
        
        function cancelChanges() {
            const currentValues = {
                maxQueueCapacity: parseInt(document.getElementById('maxQueueCapacity').value),
                skipTimeout: parseInt(document.getElementById('skipTimeout').value),
                skipTimeoutUnit: document.getElementById('skipTimeoutUnit').value,
                sessionTimeout: parseInt(document.getElementById('sessionTimeout').value)
            };
            
            const hasChanges = JSON.stringify(currentValues) !== JSON.stringify(originalSettings);
            
            if (hasChanges) {
                if (confirm('You have unsaved changes. Are you sure you want to cancel?')) {
                    window.history.back();
                }
            } else {
                window.history.back();
            }
        }
        
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' 
                ? 'bg-green-100 border-green-500 text-green-700' 
                : 'bg-red-100 border-red-500 text-red-700';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            const alertHTML = `
                <div class="${alertClass} border-l-4 p-4 mb-6 rounded" role="alert">
                    <div class="flex">
                        <div class="py-1"><i class="fas ${icon} mr-2"></i></div>
                        <div>
                            <p class="font-bold">${type === 'success' ? 'Success' : 'Error'}</p>
                            <p class="text-sm">${message}</p>
                        </div>
                    </div>
                </div>
            `;
            
            alertContainer.innerHTML = alertHTML;
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
            
            // Scroll to top to show alert
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Warn about unsaved changes
        window.addEventListener('beforeunload', function (e) {
            const currentValues = {
                maxQueueCapacity: parseInt(document.getElementById('maxQueueCapacity').value),
                skipTimeout: parseInt(document.getElementById('skipTimeout').value),
                skipTimeoutUnit: document.getElementById('skipTimeoutUnit').value,
                sessionTimeout: parseInt(document.getElementById('sessionTimeout').value)
            };
            
            const hasChanges = JSON.stringify(currentValues) !== JSON.stringify(originalSettings);
            
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>