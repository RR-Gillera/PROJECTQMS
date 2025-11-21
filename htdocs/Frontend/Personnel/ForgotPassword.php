<?php
// Forgot Password Page for SeQueueR
session_start();

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $resetType = $_POST['reset_type'] ?? 'email';
    
    // Basic validation
    if (empty($email)) {
        $error_message = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // TODO: Implement actual password reset logic
        // For now, simulate successful request
        $success_message = 'Password reset instructions have been sent to your email address.';
        
        // Store email in session for verification step
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_type'] = $resetType;
    }
}

// Handle verification code submission
if (isset($_POST['verify_code'])) {
    $verification_code = $_POST['verification_code'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($verification_code)) {
        $error_message = 'Please enter the verification code.';
    } elseif (empty($new_password)) {
        $error_message = 'Please enter a new password.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        // TODO: Implement actual password reset verification
        // For now, simulate successful reset
        $success_message = 'Your password has been successfully reset. You can now log in with your new password.';
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_type']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>Forgot Password - SeQueueR</title>
    <link rel="icon" type="image/png" href="/Frontend/favicon.php">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .success-message {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-white text-gray-700">
    <?php include 'LoginHeader.php'; ?>
    
    <main class="flex-grow flex justify-center items-center bg-gradient-to-r from-white via-slate-300 to-slate-600 relative overflow-hidden py-12">
        <img alt="University of Cebu campus buildings with modern architecture, blue sky, and trees, faded and tinted blue as background" 
             aria-hidden="true" 
             class="absolute inset-0 w-full h-full object-cover opacity-70 pointer-events-none select-none" 
             src="https://placehold.co/1920x1080/png?text=University+of+Cebu+Campus+Buildings+Background"/>
        
        <div class="relative bg-white rounded-lg shadow-lg max-w-md w-full p-8 space-y-6 fade-in">
            <!-- Header -->
            <div class="flex justify-center">
                <div class="bg-yellow-400 rounded-full p-4">
                    <i class="fas fa-key text-blue-700 text-xl"></i>
                </div>
            </div>
            
            <div class="text-center">
                <h2 class="text-blue-700 font-extrabold text-xl mb-2">Reset Your Password</h2>
                <p class="text-gray-600 text-sm">
                    <?php if (isset($_SESSION['reset_email'])): ?>
                        Enter the verification code sent to your email and create a new password
                    <?php else: ?>
                        Enter your email address to receive password reset instructions
                    <?php endif; ?>
                </p>
            </div>

            <!-- Error/Success Messages -->
            <?php if (isset($error_message)): ?>
                <div class="bg-red-50 border border-red-200 rounded-md p-4 success-message">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <span class="text-red-700 text-sm"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($success_message)): ?>
                <div class="bg-green-50 border border-green-200 rounded-md p-4 success-message">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <span class="text-green-700 text-sm"><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['reset_email']) && !isset($success_message)): ?>
                <!-- Verification Code Form -->
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-blue-700 font-semibold text-sm mb-1" for="verification_code">
                            Verification Code
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-blue-700">
                                <i class="fas fa-shield-alt"></i>
                            </span>
                            <input type="text" 
                                   id="verification_code" 
                                   name="verification_code" 
                                   placeholder="Enter 6-digit code"
                                   maxlength="6"
                                   class="w-full border border-gray-300 rounded-md py-2 pl-10 pr-3 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent text-center text-lg tracking-widest"
                                   required/>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Code sent to: <?php echo htmlspecialchars($_SESSION['reset_email']); ?>
                        </p>
                    </div>

                    <div>
                        <label class="block text-blue-700 font-semibold text-sm mb-1" for="new_password">
                            New Password
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-blue-700">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   placeholder="Enter new password"
                                   minlength="8"
                                   class="w-full border border-gray-300 rounded-md py-2 pl-10 pr-10 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                                   required/>
                            <span class="absolute inset-y-0 right-3 flex items-center text-gray-400 cursor-pointer" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye" id="new_password_toggle"></i>
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters long</p>
                    </div>

                    <div>
                        <label class="block text-blue-700 font-semibold text-sm mb-1" for="confirm_password">
                            Confirm New Password
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-blue-700">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Confirm new password"
                                   minlength="8"
                                   class="w-full border border-gray-300 rounded-md py-2 pl-10 pr-10 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                                   required/>
                            <span class="absolute inset-y-0 right-3 flex items-center text-gray-400 cursor-pointer" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="confirm_password_toggle"></i>
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-col space-y-3">
                        <button type="submit" 
                                name="verify_code"
                                class="w-full bg-blue-700 text-white py-2 px-4 rounded-md hover:bg-blue-800 transition font-medium">
                            Reset Password
                        </button>
                        <button type="button" 
                                onclick="resendCode()"
                                class="w-full text-blue-700 py-2 px-4 rounded-md hover:bg-blue-50 transition font-medium">
                            Resend Code
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <!-- Email Input Form -->
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-blue-700 font-semibold text-sm mb-1" for="email">
                            Email Address
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-blue-700">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Enter your email address"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   class="w-full border border-gray-300 rounded-md py-2 pl-10 pr-3 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent"
                                   required/>
                        </div>
                    </div>

                    <div>
                        <label class="block text-blue-700 font-semibold text-sm mb-1">Reset Method</label>
                        <div class="space-y-2">
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="reset_type" value="email" checked class="mr-3 text-blue-600">
                                <span class="text-sm text-gray-700">Send reset link via email</span>
                            </label>
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="reset_type" value="sms" class="mr-3 text-blue-600">
                                <span class="text-sm text-gray-700">Send verification code via SMS</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col space-y-3">
                        <button type="submit" 
                                class="w-full bg-blue-700 text-white py-2 px-4 rounded-md hover:bg-blue-800 transition font-medium">
                            Send Reset Instructions
                        </button>
                        <a href="Signin.php" 
                           class="w-full text-center text-blue-700 py-2 px-4 rounded-md hover:bg-blue-50 transition font-medium">
                            Back to Login
                        </a>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Help Section -->
            <div class="border-t border-gray-200 pt-4">
                <div class="text-center">
                    <p class="text-xs text-gray-500 mb-2">Need help?</p>
                    <div class="flex justify-center space-x-4">
                        <a href="mailto:support@uc.edu.ph" class="text-blue-600 hover:text-blue-800 text-xs">
                            <i class="fas fa-envelope mr-1"></i>
                            Email Support
                        </a>
                        <a href="tel:+63-32-123-4567" class="text-blue-600 hover:text-blue-800 text-xs">
                            <i class="fas fa-phone mr-1"></i>
                            Call Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggle = document.getElementById(fieldId + '_toggle');
            
            if (field.type === 'password') {
                field.type = 'text';
                toggle.classList.remove('fa-eye');
                toggle.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                toggle.classList.remove('fa-eye-slash');
                toggle.classList.add('fa-eye');
            }
        }

        // Resend verification code
        function resendCode() {
            if (confirm('Resend verification code to your email?')) {
                // TODO: Implement resend code functionality
                alert('Verification code has been resent to your email.');
            }
        }

        // Auto-format verification code input
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('verification_code');
            if (codeInput) {
                codeInput.addEventListener('input', function(e) {
                    // Only allow numbers
                    e.target.value = e.target.value.replace(/[^0-9]/g, '');
                    
                    // Auto-focus next input if 6 digits entered
                    if (e.target.value.length === 6) {
                        document.getElementById('new_password').focus();
                    }
                });
            }

            // Password confirmation validation
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword) {
                confirmPassword.addEventListener('input', function() {
                    const newPassword = document.getElementById('new_password').value;
                    if (this.value && this.value !== newPassword) {
                        this.setCustomValidity('Passwords do not match');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });
    </script>
</body>
</html>
