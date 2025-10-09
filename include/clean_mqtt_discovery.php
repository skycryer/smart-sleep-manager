<?PHP
/* Smart Sleep Manager - Clean MQTT Discovery
 * Removes old sensors and forces new Discovery
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

echo "Cleaning old MQTT Discovery and creating new sensors...\n";
echo "Script: $script_path\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "----------------------------------------\n";

// Remove discovery files to force complete refresh
$discovery_file = "/tmp/smart_sleep_discovery_hour";
if (file_exists($discovery_file)) {
    unlink($discovery_file);
    echo "Removed discovery hour lock file\n";
}

// Get current configuration to determine hostname and topics
$config_file = "/boot/config/plugins/$plugin/$plugin.cfg";
$config = [];
if (file_exists($config_file)) {
    $config_content = file_get_contents($config_file);
    foreach (explode("\n", $config_content) as $line) {
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value, '"');
        }
    }
}

// Get hostname
$hostname = trim(shell_exec('hostname'));
echo "Hostname: $hostname\n";

// Clean old sensors by sending empty configs (if MQTT is enabled)
if (isset($config['mqtt_enabled']) && $config['mqtt_enabled'] === 'true' && !empty($config['mqtt_host'])) {
    echo "MQTT is enabled, cleaning old sensors...\n";
    
    $mqtt_host = $config['mqtt_host'];
    $mqtt_port = $config['mqtt_port'] ?? '1883';
    $mqtt_user = $config['mqtt_username'] ?? '';
    $mqtt_pass = $config['mqtt_password'] ?? '';
    $topic_prefix = $config['mqtt_topic_prefix'] ?? 'unraid/smart-sleep';
    
    $base_topic = "homeassistant/sensor/" . strtolower($hostname) . "_smart_sleep";
    
    // List of all sensor topics to clean (remove them before recreating)
    $old_sensors = [
        "$base_topic/test_sensor/config",  // Old test sensor if it exists
        "$base_topic/status/config",
        "$base_topic/uptime/config",
        "$base_topic/network_rate/config",
        "$base_topic/active_disks/config",
        "$base_topic/sleep_timer/config",
        "$base_topic/hostname/config",
        "$base_topic/last_check/config"
    ];
    
    // Check if mosquitto_pub is available (Docker or host)
    $mosquitto_cmd = '';
    
    // Check Docker first
    $docker_check = shell_exec('docker ps --filter "name=mosquitto" --format "{{.Names}}" 2>/dev/null');
    if (!empty(trim($docker_check))) {
        $mosquitto_cmd = "docker exec mosquitto mosquitto_pub";
        echo "Using Docker mosquitto container\n";
    } else {
        // Check host system
        $host_check = shell_exec('which mosquitto_pub 2>/dev/null');
        if (!empty(trim($host_check))) {
            $mosquitto_cmd = "mosquitto_pub";
            echo "Using host mosquitto_pub\n";
        }
    }
    
    if (!empty($mosquitto_cmd)) {
        // Build auth parameters
        $auth_params = "";
        if (!empty($mqtt_user)) {
            $auth_params .= " -u '$mqtt_user'";
            if (!empty($mqtt_pass)) {
                $auth_params .= " -P '$mqtt_pass'";
            }
        }
        
        foreach ($old_sensors as $topic) {
            $cmd = "$mosquitto_cmd -h $mqtt_host -p $mqtt_port$auth_params -t '$topic' -m '' -r";
            echo "Removing sensor: $topic\n";
            shell_exec($cmd . " 2>&1");
        }
        echo "Old sensors removal attempted\n";
    } else {
        echo "WARNING: mosquitto_pub not available, cannot clean old sensors\n";
    }
} else {
    echo "MQTT not enabled or configured, skipping cleanup\n";
}

echo "----------------------------------------\n";

// Execute the script with force discovery
$command = $script_path . " --force-discovery 2>&1";

echo "Executing: $command\n";
echo "----------------------------------------\n";

// Execute and capture output
$output = shell_exec($command);

echo $output;

echo "\n----------------------------------------\n";
echo "MQTT Discovery cleanup and recreation completed.\n";
echo "Check Home Assistant → Settings → Devices & Services → MQTT\n";
echo "Old sensors should be removed and new sensors should appear.\n";
echo "You may need to restart Home Assistant if sensors don't update immediately.\n";
?>