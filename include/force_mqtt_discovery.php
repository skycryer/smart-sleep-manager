<?PHP
/* Smart Sleep Manager - Force MQTT Discovery
 * Forces MQTT Discovery to run immediately, bypassing the hourly limit
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

echo "Forcing MQTT Discovery messages...\n";
echo "Script: $script_path\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "----------------------------------------\n";

// Remove the discovery hour file to force discovery on next run
$discovery_file = "/tmp/smart_sleep_discovery_hour";
if (file_exists($discovery_file)) {
    unlink($discovery_file);
    echo "Removed discovery hour lock file\n";
}

// Execute the script with special flag to force discovery
$command = $script_path . " --force-discovery 2>&1";

echo "Executing: $command\n";
echo "----------------------------------------\n";

// Execute and capture output
$output = shell_exec($command);

echo $output;

echo "\n----------------------------------------\n";
echo "MQTT Discovery force completed.\n";
echo "Check Home Assistant → Settings → Devices & Services → MQTT\n";
echo "New sensors should appear under 'Skynas Smart Sleep' device.\n";
?>