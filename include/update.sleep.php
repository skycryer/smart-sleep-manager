<?PHP
/* Smart Sleep Manager - Configuration Update Handler
 * Processes form submissions and updates configuration
 */

$plugin = 'smart.sleep.manager';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Wrappers.php";

// Configuration file path
$config_file = "/boot/config/plugins/$plugin/$plugin.cfg";

// Ensure config directory exists
$config_dir = dirname($config_file);
if (!is_dir($config_dir)) {
    mkdir($config_dir, 0755, true);
}

// Auto-migration: Add missing MQTT fields to existing config
if (file_exists($config_file)) {
    $existing_config = file_get_contents($config_file);
    $needs_migration = false;
    
    // Check if MQTT fields are missing
    if (strpos($existing_config, 'mqtt_enabled=') === false) {
        $needs_migration = true;
        
        // Add MQTT defaults to existing config
        $mqtt_defaults = [
            'mqtt_enabled="false"',
            'mqtt_host=""',
            'mqtt_port="1883"',
            'mqtt_username=""',
            'mqtt_password=""',
            'mqtt_topic_prefix="unraid/smart-sleep"',
            'mqtt_retain="true"'
        ];
        
        $existing_config = rtrim($existing_config) . "\n" . implode("\n", $mqtt_defaults) . "\n";
        file_put_contents($config_file, $existing_config);
        
        error_log("Smart Sleep Manager: Auto-migrated config to add MQTT fields");
    }
}

// Get form data
$enabled = $_POST['enabled'] ?? 'false';
$idle_time_minutes = (int)($_POST['idle_time_minutes'] ?? 15);
$sleep_method = $_POST['sleep_method'] ?? 'dynamix_s3';

// Handle monitor_disks from dropdownchecklist plugin (comes as space-separated string) or array
$monitor_disks = $_POST['monitor_disks'] ?? '';
if (is_array($monitor_disks)) {
    $monitor_disks_list = implode(' ', $monitor_disks);
} else {
    // dropdownchecklist sends space-separated string
    $monitor_disks_list = trim($monitor_disks);
}

// Fallback to old field names for backward compatibility
if (empty($monitor_disks_list)) {
    $monitor_disks_list = $_POST['monitor_disks_list'] ?? $_POST['array_disks_list'] ?? '';
}

$array_disks_list = $monitor_disks_list; // For backward compatibility in config

$network_monitoring = $_POST['network_monitoring'] ?? 'true';
$network_interface = $_POST['network_interface'] ?? 'eth0';
$network_threshold = (int)($_POST['network_threshold'] ?? 102400);
$telegram_enabled = $_POST['telegram_enabled'] ?? 'false';
$telegram_bot_token = $_POST['telegram_bot_token'] ?? '';
$telegram_chat_id = $_POST['telegram_chat_id'] ?? '';
$telegram_notify_standby = $_POST['telegram_notify_standby'] ?? 'true';
$telegram_notify_sleep = $_POST['telegram_notify_sleep'] ?? 'true';
$telegram_notify_blocked = $_POST['telegram_notify_blocked'] ?? 'false';
$mqtt_enabled = $_POST['mqtt_enabled'] ?? 'false';
$mqtt_host = $_POST['mqtt_host'] ?? '';
$mqtt_port = (int)($_POST['mqtt_port'] ?? 1883);
$mqtt_username = $_POST['mqtt_username'] ?? '';
$mqtt_password = $_POST['mqtt_password'] ?? '';
$mqtt_topic_prefix = $_POST['mqtt_topic_prefix'] ?? 'unraid/smart-sleep';
$mqtt_retain = $_POST['mqtt_retain'] ?? 'true';
$wol_options = $_POST['wol_options'] ?? 'g';
$restart_samba = $_POST['restart_samba'] ?? 'true';
$force_gigabit = $_POST['force_gigabit'] ?? 'false';
$dhcp_renewal = $_POST['dhcp_renewal'] ?? 'false';
$cron_schedule = $_POST['cron_schedule'] ?? '*/5 * * * *';

// Validate input
if ($idle_time_minutes < 1 || $idle_time_minutes > 1440) {
    $idle_time_minutes = 15;
}

if ($network_threshold < 0) {
    $network_threshold = 102400;
}

if ($mqtt_port < 1 || $mqtt_port > 65535) {
    $mqtt_port = 1883;
}

// Validate cron schedule format (basic validation)
if (!preg_match('/^[\d\*\/,-]+\s+[\d\*\/,-]+\s+[\d\*\/,-]+\s+[\d\*\/,-]+\s+[\d\*\/,-]+$/', $cron_schedule)) {
    $cron_schedule = '*/5 * * * *'; // Default fallback
}

// Sanitize telegram inputs
$telegram_bot_token = preg_replace('/[^0-9A-Za-z:_-]/', '', $telegram_bot_token);
$telegram_chat_id = preg_replace('/[^0-9-]/', '', $telegram_chat_id);

// Sanitize MQTT inputs
$mqtt_host = preg_replace('/[^0-9A-Za-z.\-]/', '', $mqtt_host);
$mqtt_username = trim($mqtt_username);
$mqtt_password = trim($mqtt_password);
$mqtt_topic_prefix = preg_replace('/[^0-9A-Za-z\/\-_]/', '', $mqtt_topic_prefix);

// Create configuration content
$config_content = [
    "enabled=\"$enabled\"",
    "idle_time_minutes=\"$idle_time_minutes\"",
    "sleep_method=\"$sleep_method\"",
    "monitor_disks=\"$monitor_disks_list\"",
    "array_disks=\"$array_disks_list\"", // Keep for backward compatibility
    "network_monitoring=\"$network_monitoring\"",
    "network_interface=\"$network_interface\"",
    "network_threshold=\"$network_threshold\"",
    "telegram_enabled=\"$telegram_enabled\"",
    "telegram_bot_token=\"$telegram_bot_token\"",
    "telegram_chat_id=\"$telegram_chat_id\"",
    "telegram_notify_standby=\"$telegram_notify_standby\"",
    "telegram_notify_sleep=\"$telegram_notify_sleep\"",
    "telegram_notify_blocked=\"$telegram_notify_blocked\"",
    "mqtt_enabled=\"$mqtt_enabled\"",
    "mqtt_host=\"$mqtt_host\"",
    "mqtt_port=\"$mqtt_port\"",
    "mqtt_username=\"$mqtt_username\"",
    "mqtt_password=\"$mqtt_password\"",
    "mqtt_topic_prefix=\"$mqtt_topic_prefix\"",
    "mqtt_retain=\"$mqtt_retain\"",
    "wol_options=\"$wol_options\"",
    "restart_samba=\"$restart_samba\"",
    "force_gigabit=\"$force_gigabit\"",
    "dhcp_renewal=\"$dhcp_renewal\"",
    "cron_schedule=\"$cron_schedule\""
];

// Write configuration file
file_put_contents($config_file, implode("\n", $config_content) . "\n");

// Debug: Log what we saved (remove this after testing)
error_log("Smart Sleep Manager Config Save Debug:");
error_log("MQTT Enabled: $mqtt_enabled");
error_log("MQTT Host: $mqtt_host");
error_log("MQTT Port: $mqtt_port");
error_log("Config file contents: " . file_get_contents($config_file));

// Update cron job based on enabled status and custom schedule
$cron_job = "$cron_schedule /usr/local/emhttp/plugins/$plugin/scripts/smart_sleep.sh >/dev/null 2>&1";

// Get current crontab
exec('crontab -l 2>/dev/null', $current_cron, $return_code);

// Remove existing smart sleep cron jobs
$filtered_cron = [];
foreach ($current_cron as $line) {
    if (strpos($line, 'smart_sleep.sh') === false) {
        $filtered_cron[] = $line;
    }
}

// Add cron job if enabled
if ($enabled === 'true') {
    $filtered_cron[] = $cron_job;
}

// Update crontab
$cron_content = implode("\n", $filtered_cron);
if (!empty($filtered_cron)) {
    $cron_content .= "\n";
}

$temp_cron = tempnam('/tmp', 'cron');
file_put_contents($temp_cron, $cron_content);
exec("crontab $temp_cron");
unlink($temp_cron);

// Log the configuration update
$log_message = date('Y-m-d H:i:s') . " - Smart Sleep Manager configuration updated\n";
file_put_contents('/tmp/smart-sleep.log', $log_message, FILE_APPEND | LOCK_EX);

echo "Configuration saved successfully!";
?>