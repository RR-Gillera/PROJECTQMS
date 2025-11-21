<?php
// Backend-ready structure - ready for future implementation
session_start();

// Service types for the form
$services = [
    'good-moral' => 'Request for Good Moral Certificate',
    'insurance-payment' => 'Insurance Payment',
    'approval-letter' => 'Submission of Approval/Transmittal Letter',
    'temporary-gate-pass' => 'Request for Temporary Gate Pass',
    'uniform-exemption' => 'Request for Uniform Exemption',
    'enrollment-transfer' => 'Enrollment/Transfer'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['services']) && is_array($_POST['services'])) {
        $_SESSION['selected_services'] = $_POST['services'];
        header('Location: create_ticket.php');
        // Redirect to Step 3
        header('Location: QueueRequest3.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>Queue Number Request</title>
    <title>SeQueueR Request Step 2</title>
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
        /* Custom checkbox styling - blue background with white check */
        input[type="checkbox"].service-checkbox {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            background-color: white;
            cursor: pointer;
            position: relative;
        }
        input[type="checkbox"].service-checkbox:checked {
            background-color: #00417B;
            border-color: #00417B;
        }
        input[type="checkbox"].service-checkbox:checked::after {
            content: '✓';
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
            font-size: 14px;
            line-height: 1;
        }
        input[type="checkbox"].service-checkbox:focus {
            outline: 2px solid #00417B;
            outline-offset: 2px;
        }
        .error-message {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <?php include 'Header.php'; ?>
    
    <main class="flex-grow flex items-start justify-center pt-20 pb-20 relative overflow-hidden">
        <div class="relative bg-white rounded-lg shadow-lg max-w-xl w-full p-8" style="box-shadow: 0 8px 24px rgb(0 0 0 / 0.1);">
                <div class="flex justify-center mb-6">
                    <div class="bg-yellow-100 rounded-full p-4">
                        <i class="fas fa-ticket-alt text-yellow-400 text-xl"></i>
                    </div>
                </div>
                <h2 class="text-blue-900 font-extrabold text-xl text-center mb-2">Request Your Queue Number</h2>
                <p class="text-center text-slate-600 mb-6 text-sm">Please provide the following information to get your queue number</p>
                <div class="flex items-center justify-between text-xs md:text-sm mb-4">
                    <span class="font-semibold text-blue-900">
                        Step 2 of 3
                    </span>
                    <span class="text-gray-500">
                        Service Request
                    </span>
                </div>
                <div class="w-full h-1 rounded-full bg-slate-300 mb-6 relative">
                    <div class="h-1 rounded-full bg-yellow-400 w-[67%]"></div>
                </div>
                <hr class="border-slate-200 mb-6"/>
                
                <form action="QueueRequest2.php" method="POST" id="serviceForm">
                <label class="block mb-1 text-sm font-normal text-slate-900" for="service-type">
                    Select Service Type
                    <span class="text-red-600">*</span>
                </label>
                <p class="text-gray-500 text-xs mb-3">
                    Select the service(s) you need assistance with
                </p>
                
                <!-- Error message container -->
                <div id="errorMessage" class="hidden bg-red-50 border border-red-200 rounded-md p-3 mb-4 error-message">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                        <span class="text-red-700 text-sm" id="errorText"></span>
                    </div>
                </div>
                
                <fieldset class="bg-white border border-gray-200 rounded-md p-5 space-y-4" id="service-type">
                    <?php foreach ($services as $key => $service): ?>
                    <div class="flex items-center">
                    <input class="service-checkbox" 
                           id="<?php echo $key; ?>" 
                           name="services[]" 
                           value="<?php echo $key; ?>"
                           type="checkbox"/>
                        <label class="ml-3 text-gray-700 text-sm cursor-pointer" for="<?php echo $key; ?>">
                            <?php echo htmlspecialchars($service); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </fieldset>
                
                <div class="mt-8 flex justify-center" style="gap: 80px;">
                    <button class="flex items-center gap-2 border border-gray-300 rounded-md text-gray-700 text-sm hover:bg-gray-100 transition font-medium" 
                            type="button" onclick="window.location.href='QueueRequest.php'" style="padding: 16px 32px; width: 130px; height: 36px; justify-content: center;">
                        <i class="fas fa-arrow-left text-sm"></i>
                        Back
                    </button>
                    <button class="bg-blue-900 text-white rounded-md text-sm hover:bg-blue-800 transition flex items-center justify-center gap-2 font-medium" 
                            type="submit" style="padding: 16px 32px; width: 130px; height: 36px;">
                        Next
                        <i class="fas fa-arrow-right text-sm"></i>
                    </button>
                </div>
            </form>
        </div>
    </main>
    
    <?php include '../Footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('serviceForm');
            const checkboxes = document.querySelectorAll('.service-checkbox');
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            // No maximum selection limit

            // Handle checkbox changes
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // Clear any previous errors when the selection changes
                    hideError();
                });
            });

            // Handle form submission
            form.addEventListener('submit', function(e) {
                const checkedBoxes = document.querySelectorAll('.service-checkbox:checked');
                
                if (checkedBoxes.length === 0) {
                    e.preventDefault();
                    showError('Please select at least one service.');
                    return false;
                }
                
                // No maximum selection limit
                
                // TODO: Add backend processing here
                // For now, just show success message
                console.log('Selected services:', Array.from(checkedBoxes).map(cb => cb.value));
            });

            function showError(message) {
                errorText.textContent = message;
                errorMessage.classList.remove('hidden');
                errorMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            function hideError() {
                errorMessage.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
