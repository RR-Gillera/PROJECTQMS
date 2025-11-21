<?php
// Session and DB-backed login for SeQueueR
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $loginError = 'Invalid request. Please try again.';
    } else {
        require_once __DIR__ . '/../../config.php';

        $username = trim($_POST['username'] ?? ''); // StudentID (may contain dashes)
        $password = trim((string)($_POST['password'] ?? ''));

        if ($username === '' || $password === '') {
            $loginError = 'Please enter your ID number and password.';
        } else {
            // StudentID is stored as int in database, extract digits from input
            $studentIdDigits = preg_replace('/\D+/', '', $username);
            
            if (empty($studentIdDigits)) {
                $loginError = 'Invalid Student ID format.';
            } else {
                $studentId = (int)$studentIdDigits;

                $stmt = $conn->prepare('SELECT FullName, StudentID, Email, Course, YearLevel, Password, Role FROM Accounts WHERE StudentID = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $studentId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result ? $result->fetch_assoc() : null;
                    $stmt->close();

                if ($row) {
                    $dbPassword = trim((string)$row['Password']);
                    // Accept both legacy MD5 hashes (32 hex chars) and plain-text for flexibility
                    if (preg_match('/^[a-f0-9]{32}$/i', $dbPassword)) {
                        $passwordMatches = hash_equals(strtolower($dbPassword), strtolower(md5($password)));
                    } else {
                        $passwordMatches = hash_equals($dbPassword, $password);
                    }

                    if ($passwordMatches) {
                        // Get role from database, default to 'Working' if null or empty
                        $dbRole = trim((string)($row['Role'] ?? ''));
                        $role = $dbRole !== '' ? $dbRole : 'Working';

                        // Update LastActivity timestamp to track active status
                        // Use PHP's date() to ensure correct timezone
                        $currentDateTime = date('Y-m-d H:i:s');
                        $updateStmt = $conn->prepare('UPDATE Accounts SET LastActivity = ? WHERE StudentID = ?');
                        if ($updateStmt) {
                            $updateStmt->bind_param('si', $currentDateTime, $studentId);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }

                        // Assign counter to both worker and admin
                        $counterNumber = null;
                        if (strtolower($role) === 'working' || strtolower($role) === 'admin') {
                            require_once __DIR__ . '/admin_functions.php';
                            // Use the actual StudentID from the row (int) to match DB type
                            $counterNumber = assignCounterToWorker($conn, (int)$row['StudentID']);

                            if ($counterNumber === false) {
                                // All counters are occupied – show error and DO NOT log in
                                $loginError = 'All counters are currently occupied. Please try again later.';
                            }
                        }

                        // Only create session & redirect if login is allowed
                        if (!($counterNumber === false && (strtolower($role) === 'working' || strtolower($role) === 'admin'))) {
                            $_SESSION['user'] = [
                                'studentId'     => (string)$row['StudentID'],
                                'fullName'      => $row['FullName'],
                                'email'         => $row['Email'],
                                'course'        => $row['Course'],
                                'yearLevel'     => $row['YearLevel'],
                                'role'          => $role,
                                'counterNumber' => $counterNumber
                            ];

                            // Redirect based on role
                            if (strtolower($role) === 'admin') {
                                header('Location: Admin/Dashboard.php');
                            } else {
                                // Default to Working dashboard for 'Working' role or any other role
                                header('Location: Working/Queue.php');
                            }
                            exit;
                        }
                    } else {
                        $loginError = 'Invalid credentials. Please try again.';
                    }
                } else {
                    $loginError = 'Account not found.';
                }
            } else {
                $loginError = 'Unable to process login right now.';
            }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>SeQueueR Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-white text-gray-700">
    <?php include 'LoginHeader.php'; ?>
    <main class="flex-grow flex justify-center items-center relative overflow-hidden py-12" style="background-image: url('../Assests/QueueReqPic.png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">
        <form aria-label="SeQueueR Login Form" class="relative bg-white rounded-lg shadow-md max-w-xl w-full p-10 space-y-6" method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <?php if (!empty($loginError)) { ?>
                <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php } ?>
            <div class="flex justify-center">
                <div class="bg-yellow-400 rounded-full p-4">
                    <i class="fas fa-user-graduate text-blue-700 text-xl"></i>
                </div>
            </div>
            <h2 class="text-center text-blue-700 font-extrabold text-xl">SeQueueR Login</h2>
            <p class="text-center text-gray-600 text-sm">Sign in to your queue management dashboard</p>
            <div>
                <label class="block text-blue-700 font-semibold text-sm mb-1" for="username">User Name</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-blue-700">
                        <i class="fas fa-id-badge"></i>
                    </span>
                    <input autocomplete="username" class="w-full border border-gray-300 rounded-md py-2 pl-10 pr-3 text-gray-500 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent" id="username" name="username" placeholder="e.g., WS2024-001" type="text"/>
                </div>
            </div>
            <div>
                <label class="block text-blue-700 font-semibold text-sm mb-1" for="password">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-3 flex items-center text-blue-700">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input autocomplete="current-password" class="w-full border border-gray-300 rounded-md py-2 pl-10 pr-10 text-gray-500 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent" id="password" name="password" placeholder="Enter your password" type="password"/>
                    <span aria-label="Show password" class="absolute inset-y-0 right-3 flex items-center text-gray-400 cursor-pointer" onclick="togglePassword()">
                        <i class="fas fa-eye" id="passwordToggleIcon"></i>
                    </span>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <input class="w-4 h-4 border border-gray-400 rounded-sm text-blue-600 focus:ring-blue-500" id="remember" name="remember" type="checkbox"/>
                <label class="text-sm text-gray-700 select-none" for="remember">Remember me</label>
            </div>
            <p class="text-xs text-gray-500">Only use on trusted office computers</p>
            <button class="w-full bg-blue-900 text-white font-medium rounded-md py-3 mt-2 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-700" type="submit">Login</button>
            <div class="flex justify-end">
                <a class="text-blue-600 text-sm hover:underline" href="ForgotPassword.php">Forgot Password?</a>
            </div>
            <p class="text-center text-xs text-gray-600">First time login? Check with supervisor</p>
        </form>
    </main>
    <!-- Include Footer -->
    <?php include '../Footer.php'; ?>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>