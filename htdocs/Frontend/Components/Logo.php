<?php
// Standardized Logo Component for SeQueueR
// This ensures consistent logo size, design, and style across all headers
?>

<div class="flex items-center space-x-4">
    <img alt="University of Cebu Student Affairs circular seal" 
         class="h-12 w-12 rounded-full object-cover" 
         src="<?php echo $logoPath ?? '../../sao-nobg.png'; ?>"/>
    <div class="leading-tight">
        <h1 class="text-blue-900 font-bold text-xl -mb-1">SeQueueR</h1>
        <p class="text-gray-600 text-sm">UC Student Affairs</p>
    </div>
</div>
