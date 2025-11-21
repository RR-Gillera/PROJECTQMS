<?php

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
    <!-- Header Component -->
    <?php include 'Frontend/Student/Header.php'; ?>
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
     <div class="flex flex-nowrap" style="gap: 30px;">
      <a href="/Frontend/Student/QueueRequest.php" class="bg-yellow-400 text-black font-semibold rounded-md shadow-md flex items-center gap-2 hover:brightness-110 transition text-[18px]" style="padding: 12px 24px; width: 260px; height: 50px; justify-content: center;">
       <i class="fas fa-laptop text-sm">
       </i>
       Get Queue Number
      </a>
      <a href="Frontend/About.php?ref=index" class="border border-white text-white font-semibold rounded-md flex items-center gap-2 hover:bg-white hover:text-[#00447a] transition text-[18px]" style="padding: 12px 24px; width: 260px; height: 50px; justify-content: center;">
       <i class="far fa-clock text-sm">
       </i>
       About SeQueueR
      </a>
     </div>
    </div>
    <div class="flex-1 flex justify-center md:justify-end">
     <div class="w-[320px] h-[320px] sm:w-[370px] sm:h-[370px] md:w-[420px] md:h-[420px] lg:w-[477px] lg:h-[477px] rounded-full border-8 border-white shadow-2xl flex items-center justify-center bg-white">
      <img alt="University of Cebu Student Affairs Office logo" class="w-[300px] h-[300px] sm:w-[350px] sm:h-[350px] md:w-[400px] md:h-[400px] lg:w-[457px] lg:h-[457px] object-cover rounded-full" src="Frontend/Assests/sao logo.jpg"/>
     </div>
    </div>
   </div>
  </main>
  <!-- Footer Component -->
  <?php include 'Frontend/Footer.php'; ?>
 </body>
</html>
