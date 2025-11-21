<?php
// Header component for SeQueueR
?>

<header class="bg-white border-b border-gray-300 sticky top-0 z-50">
    <div class="flex items-center justify-between py-3 px-6 md:px-10 mx-16 md:mx-32 lg:mx-48">
        <a href=" https://qmscharlie.byethost5.com/index.php" class="flex items-center hover:opacity-80 transition-opacity">
            <img alt="University of Cebu Student Affairs circular seal" class="h-12 w-12 rounded-full object-cover" src="/Frontend/Assests/SAO.png"/>
            <div class="ml-4 text-left">
                <h1 class="text-blue-900 font-bold text-xl -mb-1">
                    SeQueueR
                </h1>
                <p class="text-gray-600 text-sm">
                    UC Student Affairs
                </p>
            </div>
        </a>
        <div class="text-blue-900 text-right">
            <div id="tvTime" class="font-bold text-lg md:text-xl">--:-- --</div>
            <div id="tvDate" class="text-xs md:text-sm">--</div>
        </div>
    </div>
</header>

<script>
  (function updateClock() {
    const timeEl = document.getElementById('tvTime');
    const dateEl = document.getElementById('tvDate');
    if (timeEl && dateEl) {
      const now = new Date();
      timeEl.textContent = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
      dateEl.textContent = now.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
    }
    setTimeout(updateClock, 1000);
  })();
</script>


