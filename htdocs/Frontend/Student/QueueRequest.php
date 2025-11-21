<?php
session_start();
require_once 'db_config.php';
require_once 'check_queue_limit.php'; // Add capacity check functions

include_once __DIR__ . '/auto_cancel_trigger.php';

// Check queue capacity
$conn = getDBConnection();
$capacityInfo = checkQueueCapacity($conn);
$queueFull = !$capacityInfo['canQueue'];

// If queue is full, store message in session
if ($queueFull) {
    $_SESSION['queue_capacity_error'] = 'Queue system is currently at full capacity (' . 
                                         $capacityInfo['current'] . '/' . $capacityInfo['max'] . 
                                         '). Please try again later.';
}

$conn->close();

$courses = [
    'BS Computer Science',
    'BS Information Technology', 
    'BS Business Administration',
    'BS Accountancy',
    'BS Psychology',
    'BS Education',
    'BS Nursing',
    'BS Engineering',
    'BS Architecture',
    'BS Tourism',
    'BS Criminology',
    'BS Psychology',
    'BS Social Work',
    'BS Hotel and Restaurant Management',
    'BS Tourism Management'
];

// Initialize errors and old values
$errors = [
    'fullname' => '',
    'studentid' => '',
    'yearlevel' => '',
    'courseprogram' => ''
];

$oldFullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : ($_SESSION['fullname'] ?? '');
$oldStudentId = isset($_POST['studentid']) ? trim($_POST['studentid']) : ($_SESSION['studentid'] ?? '');
$oldYearLevel = isset($_POST['yearlevel']) ? trim($_POST['yearlevel']) : ($_SESSION['yearlevel'] ?? '');
$oldCourseProgram = isset($_POST['courseprogram']) ? trim($_POST['courseprogram']) : ($_SESSION['courseprogram'] ?? '');

// Build a normalized lookup for courses (trim + lowercase)
$normalizedCourses = array_map(function($c) { return mb_strtolower(trim($c)); }, $courses);

// Handle form submission with validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check capacity again on submission (in case it filled up during form fill)
    $conn = getDBConnection();
    $capacityInfo = checkQueueCapacity($conn);
    $conn->close();
    
    if (!$capacityInfo['canQueue']) {
        $_SESSION['queue_capacity_error'] = 'Queue system is currently at full capacity (' . 
                                             $capacityInfo['current'] . '/' . $capacityInfo['max'] . 
                                             '). Please try again later.';
        // Refresh page to show error
        header('Location: QueueRequest.php');
        exit();
    }
    
    // Validate Full Name
    if ($oldFullname === '') {
        $errors['fullname'] = 'Full name is required.';
    }

    // Validate Student ID (exactly 8 digits)
    if ($oldStudentId === '') {
        $errors['studentid'] = 'Student ID is required.';
    } elseif (!preg_match('/^\d{8}$/', $oldStudentId)) {
        $errors['studentid'] = 'Student ID must be exactly 8 numeric digits.';
    }

    // Validate Year Level
    if ($oldYearLevel === '') {
        $errors['yearlevel'] = 'Year level is required.';
    }

    // Validate Course/Program (must match one from the dropdown list)
    if ($oldCourseProgram === '') {
        $errors['courseprogram'] = 'Please select a course/program from the list.';
    } else {
        $needle = mb_strtolower(trim($oldCourseProgram));
        if (!in_array($needle, $normalizedCourses, true)) {
            $errors['courseprogram'] = 'Invalid course/program. Please select from the dropdown list.';
        } else {
            // Normalize to canonical course name as stored in $courses
            foreach ($courses as $courseItem) {
                if (mb_strtolower(trim($courseItem)) === $needle) {
                    $oldCourseProgram = $courseItem;
                    break;
                }
            }
        }
    }

    $hasErrors = array_filter($errors, fn($v) => $v !== '');

    if (!$hasErrors) {
        // Store student data in session
        $_SESSION['fullname'] = $oldFullname;
        $_SESSION['studentid'] = $oldStudentId;
        $_SESSION['yearlevel'] = $oldYearLevel;
        $_SESSION['courseprogram'] = $oldCourseProgram;
        
        // Redirect to Step 2
        header('Location: QueueRequest2.php');
        exit;
    }
}

// Handle AJAX course search
if (isset($_GET['action']) && $_GET['action'] === 'search_courses') {
    $searchTerm = $_GET['q'] ?? '';
    $filteredCourses = array_filter($courses, function($course) use ($searchTerm) {
        return stripos($course, $searchTerm) !== false;
    });
    header('Content-Type: application/json');
    echo json_encode(array_values($filteredCourses));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1" name="viewport"/>
    <title>SeQueueR Request Form</title>
    <link rel="icon" type="image/png" href="/Frontend/favicon.php">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        /* Custom scrollbar for dropdown */
        .scrollbar-thin {
            scrollbar-width: thin;
            scrollbar-color: #facc15 #e5e7eb;
        }
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #e5e7eb;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background-color: #facc15;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .alert-slide {
            animation: slideDown 0.3s ease-out;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col" style="background-image: url('../Assests/QueueReqPic.png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">
    <?php include 'Header.php'; ?>

    <main class="flex-grow flex items-start justify-center pt-20 pb-20 relative overflow-hidden">
        <form action="QueueRequest.php" aria-label="Request Your Queue Number Form" class="relative bg-white rounded-lg shadow-lg max-w-xl w-full p-8" method="POST">
            
            <!-- Queue Capacity Error Alert -->
            <?php if (isset($_SESSION['queue_capacity_error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded alert-slide">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle text-red-500 mt-1 mr-3"></i>
                    <div>
                        <p class="font-bold">Queue Full</p>
                        <p class="text-sm"><?php echo htmlspecialchars($_SESSION['queue_capacity_error']); ?></p>
                    </div>
                </div>
            </div>
            <?php 
                unset($_SESSION['queue_capacity_error']);
            endif; 
            ?>
            
            <!-- Queue Capacity Warning (75-89% full) -->
            <?php if (!$queueFull && ($capacityInfo['current'] / $capacityInfo['max']) >= 0.75 && ($capacityInfo['current'] / $capacityInfo['max']) < 0.90): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded alert-slide">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-3"></i>
                    <div>
                        <p class="font-bold">High Queue Volume</p>
                        <p class="text-sm">Queue is filling up fast. Only <?php echo $capacityInfo['remaining']; ?> slot(s) remaining.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Queue Almost Full Warning (90%+ full) -->
            <?php if (!$queueFull && ($capacityInfo['current'] / $capacityInfo['max']) >= 0.90): ?>
            <div class="bg-orange-100 border-l-4 border-orange-500 text-orange-700 p-4 mb-6 rounded alert-slide">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-orange-500 mt-1 mr-3"></i>
                    <div>
                        <p class="font-bold">Queue Almost Full</p>
                        <p class="text-sm">Only <?php echo $capacityInfo['remaining']; ?> slot(s) remaining. Submit quickly!</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="flex justify-center mb-6">
                <div class="bg-yellow-100 rounded-full p-4">
                    <i class="fas fa-ticket-alt text-yellow-400 text-xl"></i>
                </div>
            </div>
            <h2 class="text-blue-900 font-extrabold text-xl text-center mb-2">Request Your Queue Number</h2>
            <p class="text-center text-slate-600 mb-6 text-sm">Please provide the following information to get your queue number</p>
            
            <div class="flex items-center justify-between text-xs md:text-sm mb-4">
                <span class="font-semibold text-blue-900">
                    Step 1 of 3
                </span>
                <span class="text-gray-500">
                    Service Request
                </span>
            </div>
            <div class="w-full h-1 rounded-full bg-slate-300 mb-6 relative">
                <div class="h-1 rounded-full bg-yellow-400 w-[33%]"></div>
            </div>
            <hr class="border-slate-200 mb-6"/>
            
            <h3 class="text-blue-900 font-semibold mb-4 text-sm">Student Information</h3>
            
            <label class="block mb-1 text-sm font-normal text-slate-900" for="fullname">
                Full Name <span class="text-red-600">*</span>
            </label>
            <input autocomplete="name" 
                   class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 <?php echo $queueFull ? 'bg-gray-100 cursor-not-allowed' : ''; ?>" 
                   id="fullname" 
                   name="fullname" 
                   placeholder="Enter your complete name (Last, First, Middle)" 
                   required 
                   type="text" 
                   value="<?php echo htmlspecialchars($oldFullname); ?>"
                   <?php echo $queueFull ? 'disabled' : ''; ?>/>
            <?php if ($errors['fullname']) { ?>
                <p class="text-xs text-red-600 mt-1 mb-6"><?php echo htmlspecialchars($errors['fullname']); ?></p>
            <?php } else { ?>
                <div class="mb-6"></div>
            <?php } ?>
            
            <div class="flex flex-col sm:flex-row sm:space-x-6 mb-6">
                <div class="flex-1">
                    <label class="block mb-1 text-sm font-normal text-slate-900" for="studentid">
                        Student ID Number <span class="text-red-600">*</span>
                    </label>
                    <input autocomplete="student-id" 
                           class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 <?php echo $queueFull ? 'bg-gray-100 cursor-not-allowed' : ''; ?>" 
                           id="studentid" 
                           name="studentid" 
                           placeholder="e.g., 21411277" 
                           required 
                           type="text" 
                           inputmode="numeric" 
                           pattern="\d{8}" 
                           maxlength="8" 
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')" 
                           value="<?php echo htmlspecialchars($oldStudentId); ?>"
                           <?php echo $queueFull ? 'disabled' : ''; ?>/>
                    <p class="text-xs text-slate-500 mt-1">Enter your official university ID number (8 digits)</p>
                    <?php if ($errors['studentid']) { ?>
                        <p class="text-xs text-red-600 mt-1"><?php echo htmlspecialchars($errors['studentid']); ?></p>
                    <?php } ?>
                </div>
                <div class="flex-1 mt-4 sm:mt-0">
                    <label class="block mb-1 text-sm font-normal text-slate-900" for="yearlevel">
                        Year Level <span class="text-red-600">*</span>
                    </label>
                    <select class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-black focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 <?php echo $queueFull ? 'bg-gray-100 cursor-not-allowed' : ''; ?>" 
                            id="yearlevel" 
                            name="yearlevel" 
                            required
                            <?php echo $queueFull ? 'disabled' : ''; ?>>
                        <option disabled value="" <?php echo $oldYearLevel === '' ? 'selected' : ''; ?>>Select year level</option>
                        <option value="1st Year" <?php echo $oldYearLevel === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                        <option value="2nd Year" <?php echo $oldYearLevel === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                        <option value="3rd Year" <?php echo $oldYearLevel === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                        <option value="4th Year" <?php echo $oldYearLevel === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                    </select>
                    <?php if ($errors['yearlevel']) { ?>
                        <p class="text-xs text-red-600 mt-1"><?php echo htmlspecialchars($errors['yearlevel']); ?></p>
                    <?php } ?>
                </div>
            </div>
            
            <label class="block mb-1 text-sm font-normal text-slate-900" for="courseSearch">
                Course/Program <span class="text-red-600">*</span>
            </label>
            <div class="relative mb-8">
                <input aria-label="Search Course/Program" 
                       autocomplete="off" 
                       class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 <?php echo $queueFull ? 'bg-gray-100 cursor-not-allowed' : ''; ?>" 
                       id="courseSearch" 
                       placeholder="Type to search Course/Program" 
                       type="text" 
                       value="<?php echo htmlspecialchars($oldCourseProgram); ?>"
                       <?php echo $queueFull ? 'disabled' : ''; ?>/>
                <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-500">
                     <i class="fas fa-chevron-down"></i>
                </div>
                <ul class="absolute z-10 w-full max-h-48 mt-1 overflow-auto bg-white border border-slate-300 rounded-md shadow-lg scrollbar-thin hidden" 
                    id="courseDropdown" role="listbox" tabindex="-1">
                    <!-- Options will be populated by JavaScript -->
                </ul>
                <input id="courseprogram" name="courseprogram" required type="hidden" value="<?php echo htmlspecialchars($oldCourseProgram); ?>"/>
                <?php if ($errors['courseprogram']) { ?>
                    <p class="text-xs text-red-600 mt-1"><?php echo htmlspecialchars($errors['courseprogram']); ?></p>
                <?php } ?>
            </div>
            
            <div class="flex justify-center" style="gap: 80px;">
                <button class="flex items-center gap-2 border border-slate-300 rounded-md text-slate-700 text-sm hover:bg-slate-100 transition font-medium" 
                        onclick="window.location.href='https://qmscharlie.byethost5.com'" 
                        type="button" 
                        style="padding: 16px 32px; width: 130px; height: 36px; justify-content: center;">
                    <i class="fas fa-home text-sm"></i>
                    Home
                </button>
                <button class="<?php echo $queueFull ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-900 hover:bg-blue-800'; ?> text-white rounded-md text-sm transition flex items-center justify-center gap-2 font-medium" 
                        type="submit" 
                        style="padding: 16px 32px; width: 130px; height: 36px;"
                        <?php echo $queueFull ? 'disabled' : ''; ?>>
                    <?php if ($queueFull): ?>
                        <i class="fas fa-ban text-sm"></i>
                        Queue Full
                    <?php else: ?>
                        Next
                        <i class="fas fa-arrow-right text-sm"></i>
                    <?php endif; ?>
                </button>
            </div>
        </form>
    </main>

    <?php include '../Footer.php'; ?>

    <script>
        // Pass queue full status to JavaScript
        const queueFull = <?php echo $queueFull ? 'true' : 'false'; ?>;
        
        (() => {
            const input = document.getElementById('courseSearch');
            const dropdown = document.getElementById('courseDropdown');
            const hiddenInput = document.getElementById('courseprogram');
            let courses = <?php echo json_encode($courses); ?>;
            let filteredCourses = [...courses];
            let focusedIndex = -1;
            let searchTimeout;

            // If queue is full, disable interactions
            if (queueFull) {
                input.disabled = true;
                return;
            }

            // Ensure hidden is initialized if input has a valid value on load
            (function syncOnLoad() {
                const typed = (input.value || '').trim();
                const hiddenVal = (hiddenInput.value || '').trim();
                if (!hiddenVal && typed) {
                    const exact = courses.find(c => c.toLowerCase() === typed.toLowerCase());
                    if (exact) {
                        hiddenInput.value = exact;
                        input.value = exact;
                    }
                }
            })();

            // Populate dropdown with courses
            function populateDropdown(coursesToShow) {
                dropdown.innerHTML = '';
                if (coursesToShow.length === 0) {
                    dropdown.innerHTML = '<li class="px-3 py-2 text-slate-500 cursor-default select-none">No results found</li>';
                    return;
                }
                
                coursesToShow.forEach(course => {
                    const li = document.createElement('li');
                    li.className = 'cursor-pointer px-3 py-2 hover:bg-yellow-100';
                    li.setAttribute('data-value', course);
                    li.setAttribute('role', 'option');
                    li.setAttribute('tabindex', '0');
                    li.textContent = course;
                    
                    li.addEventListener('click', () => selectOption(li));
                    li.addEventListener('mouseenter', () => {
                        clearFocus();
                        li.classList.add('bg-yellow-200');
                        focusedIndex = coursesToShow.indexOf(course);
                    });
                    
                    dropdown.appendChild(li);
                });
            }

            // Toggle dropdown visibility
            function toggleDropdown(show) {
                if (show) {
                    dropdown.classList.remove('hidden');
                    input.setAttribute('aria-expanded', 'true');
                    populateDropdown(filteredCourses);
                } else {
                    dropdown.classList.add('hidden');
                    input.setAttribute('aria-expanded', 'false');
                    focusedIndex = -1;
                }
            }

            // Clear focus from all options
            function clearFocus() {
                dropdown.querySelectorAll('li').forEach(li => {
                    li.classList.remove('bg-yellow-200');
                });
            }

            // Filter courses based on search term
            function filterCourses(searchTerm) {
                const term = searchTerm.toLowerCase();
                filteredCourses = courses.filter(course => 
                    course.toLowerCase().includes(term)
                );
                populateDropdown(filteredCourses);
            }

            // Select option
            function selectOption(option) {
                if (option.classList.contains('cursor-default')) return;
                input.value = option.textContent;
                hiddenInput.value = option.getAttribute('data-value');
                input.setCustomValidity('');
                toggleDropdown(false);
                focusedIndex = -1;
            }

            // Search courses with debouncing
            function searchCourses(searchTerm) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (searchTerm.length > 0) {
                        filterCourses(searchTerm);
                    } else {
                        filteredCourses = [...courses];
                        populateDropdown(filteredCourses);
                    }
                }, 150);
            }

            // Event listeners
            input.addEventListener('click', () => {
                if (input.value.length === 0) {
                    filteredCourses = [...courses];
                }
                toggleDropdown(true);
            });

            input.addEventListener('input', (e) => {
                const value = e.target.value;
                if (value.length > 0) {
                    toggleDropdown(true);
                    searchCourses(value);
                } else {
                    hiddenInput.value = '';
                    filteredCourses = [...courses];
                    if (!dropdown.classList.contains('hidden')) {
                        populateDropdown(filteredCourses);
                    }
                }

                const typed = value.trim();
                if (typed) {
                    const exact = courses.find(c => c.toLowerCase() === typed.toLowerCase());
                    if (exact) {
                        hiddenInput.value = exact;
                        input.setCustomValidity('');
                    } else {
                        hiddenInput.value = '';
                    }
                }
            });

            input.addEventListener('blur', () => {
                const typed = input.value.trim();
                if (!typed) {
                    hiddenInput.value = '';
                    return;
                }
                const exact = courses.find(c => c.toLowerCase() === typed.toLowerCase());
                if (hiddenInput.value && hiddenInput.value.toLowerCase() === typed.toLowerCase()) {
                    input.setCustomValidity('');
                    return;
                }
                if (exact) {
                    input.value = exact;
                    hiddenInput.value = exact;
                    input.setCustomValidity('');
                } else {
                    hiddenInput.value = '';
                }
            });

            input.addEventListener('keydown', (e) => {
                const visibleOptions = Array.from(dropdown.querySelectorAll('li:not(.cursor-default)'));
                
                if (dropdown.classList.contains('hidden')) {
                    if (e.key === 'ArrowDown' || e.key === 'Enter') {
                        e.preventDefault();
                        toggleDropdown(true);
                    }
                    return;
                }

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        focusedIndex = (focusedIndex + 1) % visibleOptions.length;
                        clearFocus();
                        visibleOptions[focusedIndex].classList.add('bg-yellow-200');
                        visibleOptions[focusedIndex].scrollIntoView({ block: 'nearest' });
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        focusedIndex = (focusedIndex - 1 + visibleOptions.length) % visibleOptions.length;
                        clearFocus();
                        visibleOptions[focusedIndex].classList.add('bg-yellow-200');
                        visibleOptions[focusedIndex].scrollIntoView({ block: 'nearest' });
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (focusedIndex >= 0 && visibleOptions[focusedIndex]) {
                            selectOption(visibleOptions[focusedIndex]);
                        }
                        break;
                    case 'Escape':
                        toggleDropdown(false);
                        break;
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    toggleDropdown(false);
                }
            });

            // Form validation
            const form = document.querySelector('form');
            const studentIdInput = document.getElementById('studentid');

            if (studentIdInput) {
                studentIdInput.addEventListener('input', function() {
                    const value = this.value.trim();
                    if (value.length > 0 && value.length !== 8) {
                        this.setCustomValidity('Student ID must be exactly 8 digits.');
                    } else {
                        this.setCustomValidity('');
                    }
                });

                studentIdInput.addEventListener('blur', function() {
                    const value = this.value.trim();
                    if (value.length > 0 && !/^\d{8}$/.test(value)) {
                        this.setCustomValidity('Student ID must be exactly 8 numeric digits.');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            if (form) {
                form.addEventListener('submit', (e) => {
                    // Check if queue is full
                    if (queueFull) {
                        e.preventDefault();
                        alert('Queue is currently full. Please try again later.');
                        return false;
                    }
                    
                    // Validate Student ID
                    if (studentIdInput) {
                        const studentIdValue = studentIdInput.value.trim();
                        if (!/^\d{8}$/.test(studentIdValue)) {
                            e.preventDefault();
                            studentIdInput.setCustomValidity('Student ID must be exactly 8 numeric digits.');
                            studentIdInput.reportValidity();
                            studentIdInput.focus();
                            return false;
                        } else {
                            studentIdInput.setCustomValidity('');
                        }
                    }

                    // Validate course selection
                    const typed = input.value.trim();
                    if (typed && !hiddenInput.value) {
                        const exact = courses.find(c => c.toLowerCase() === typed.toLowerCase());
                        if (exact) {
                            input.value = exact;
                            hiddenInput.value = exact;
                        }
                    }

                    if (!hiddenInput.value) {
                        e.preventDefault();
                        input.setCustomValidity('Please select a course/program from the dropdown list.');
                        input.reportValidity();
                        input.focus();
                        return false;
                    } else {
                        input.setCustomValidity('');
                    }
                });
            }
        })();
    </script>
</body>
</html>