<?php
// Main Landing page for SeQueueR - Personnel
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Optional force-logout for troubleshooting: /Frontend/Personnel/Landing.php?logout=1
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: Landing.php');
    exit;
}
?>

<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>
   SeQueueR
  </title>
  <link rel="icon" type="image/png" href="/Frontend/favicon.php">
  <script src="https://cdn.tailwindcss.com">
  </script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
   body {
      font-family: 'Poppins', sans-serif;
    }
  </style>
 </head>
 <body class="bg-white text-gray-700">
    <!-- Simple top header (logo left, Login right) -->
    <header class="bg-white border-b border-gray-300">
      <div class="flex items-center justify-between py-3 px-6 md:px-10 mx-20 md:mx-34 lg:mx-44">
        <a href="./Landing.php" class="flex items-center hover:opacity-80 transition-opacity">
          <img alt="University of Cebu Student Affairs circular seal" class="h-12 w-12 rounded-full object-cover" src="/Frontend/Assests/sao logo.jpg"/>
          <div class="ml-4 text-left">
            <h1 class="text-blue-900 font-bold text-xl -mb-1">SeQueueR</h1>
            <p class="text-gray-600 text-sm">UC Student Affairs</p>
          </div>
        </a>
        <a href="Signin.php" class="bg-yellow-400 text-blue-900 font-semibold rounded-md shadow-sm hover:brightness-110 transition flex items-center justify-center" style="padding: 8px 16px; min-width: 92px; height: 36px;">Login</a>
      </div>
    </header>
   <main class="bg-[#00447a] text-white flex items-center" style="height: calc(100vh - 80px);">
     <div class="flex flex-col md:flex-row items-center justify-between px-6 md:px-10 gap-12 md:gap-20 w-full mx-20 md:mx-34 lg:mx-44 ">
    <div class="flex-1 space-y-6">
     <h2 class="text-[48px] font-extrabold leading-tight">
      Welcome to <span class="text-yellow-400">SeQueueR</span>
     </h2>
     <p class="text-[24px] font-semibold leading-relaxed" style="max-width: 800px;">
      Smart Queue Management for University of Cebu Student Affairs Services
     </p>
     <p class="text-[18px] font-light leading-relaxed" style="max-width: 600px;">
      Skip the long lines. Get your queue number instantly and track your turn in real-time. Make your student affairs visits more efficient and stress-free.
     </p>
     <div class="flex flex-nowrap items-center" style="gap: 30px; overflow-x: auto;">
      <a href="Signin.php" class="bg-yellow-400 text-black font-semibold rounded-md shadow-md flex items-center gap-2 hover:brightness-110 transition text-[18px] shrink-0" style="padding: 12px 24px; width: 260px; height: 50px; justify-content: center;">
       <i class="fas fa-laptop text-sm">
       </i>
       Manage Queue
      </a>
      <a href="/Frontend/About.php?ref=landing" class="border border-white text-white font-semibold rounded-md flex items-center gap-2 hover:bg-white hover:text-[#00447a] transition text-[18px] shrink-0" style="padding: 12px 24px; width: 260px; height: 50px; justify-content: center;">
       <i class="far fa-clock text-sm">
       </i>
       About SeQueueR
      </a>
     </div>
    </div>
    <div class="flex-1 flex justify-center md:justify-end">
     <div class="w-[320px] h-[320px] sm:w-[370px] sm:h-[370px] md:w-[420px] md:h-[420px] lg:w-[477px] lg:h-[477px] rounded-full border-8 border-white shadow-2xl flex items-center justify-center bg-white">
       <img alt="University of Cebu Student Affairs Office logo" class="w-[300px] h-[300px] sm:w-[350px] sm:h-[350px] md:w-[400px] md:h-[400px] lg:w-[457px] lg:h-[457px] object-cover rounded-full" src="/Frontend/Assests/sao logo.jpg"/>
     </div>
    </div>
   </div>
  </main>
  <!-- Footer Component -->
  <?php include '../Footer.php'; ?>
 </body>
</html>