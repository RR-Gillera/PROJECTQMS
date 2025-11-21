<?php
/**
 * Get MAC Address Helper
 * Server-side script to detect MAC address (limited by browser security)
 */

function getClientMacAddress() {
    $macAddress = null;
    
    // Try to get MAC from ARP table (works on local network only)
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    // Windows
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = shell_exec("arp -a $ipAddress");
        if ($output) {
            preg_match('/([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/', $output, $matches);
            if (isset($matches[0])) {
                $macAddress = str_replace(':', '-', strtoupper($matches[0]));
            }
        }
    } 
    // Linux/Unix
    else {
        $output = shell_exec("arp -n $ipAddress");
        if ($output) {
            preg_match('/([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/', $output, $matches);
            if (isset($matches[0])) {
                $macAddress = str_replace(':', '-', strtoupper($matches[0]));
            }
        }
    }
    
    return $macAddress;
}

// Return as JSON
header('Content-Type: application/json');
echo json_encode([
    'ip' => $_SERVER['REMOTE_ADDR'],
    'mac' => getClientMacAddress(),
    'note' => 'MAC detection may not work due to network/browser security. Please enter manually.'
]);
?>
