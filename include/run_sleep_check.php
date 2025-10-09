<?PHP
/* Smart Sleep Manager - Manual Sleep Check Runner
 * Executes the sleep check script manually via web interface
 */

header('Content-Type: text/plain');

// Security check - only allow from local webGui
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

$plugin = 'smart.sleep.manager';
$script_path = "/usr/local/emhttp/plugins/$plugin/scripts/smart_sleep.sh";

// Check if script exists and is executable
if (!file_exists($script_path)) {
    echo "ERROR: Sleep script not found at $script_path\n";
    exit(1);
}

if (!is_executable($script_path)) {
    echo "ERROR: Sleep script is not executable\n";
    exit(1);
}

echo "Starting manual sleep check...\n";
echo "Script: $script_path\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "----------------------------------------\n";

// Execute the script and capture output
$descriptorspec = array(
   0 => array("pipe", "r"),  // stdin
   1 => array("pipe", "w"),  // stdout
   2 => array("pipe", "w")   // stderr
);

$process = proc_open($script_path, $descriptorspec, $pipes);

if (is_resource($process)) {
    // Close stdin
    fclose($pipes[0]);
    
    // Read stdout
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    
    // Read stderr
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    
    // Wait for process to complete
    $return_value = proc_close($process);
    
    echo "STDOUT:\n";
    echo $stdout;
    
    if (!empty($stderr)) {
        echo "\nSTDERR:\n";
        echo $stderr;
    }
    
    echo "\nReturn code: $return_value\n";
    echo "----------------------------------------\n";
    echo "Manual sleep check completed.\n";
    echo "Check /tmp/smart-sleep.log for detailed logs.\n";
    
} else {
    echo "ERROR: Failed to start sleep check process\n";
}
?>