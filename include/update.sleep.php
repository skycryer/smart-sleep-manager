<?PHP
/* Smart Sleep Manager - Configuration Update Handler
 * Processes form submissions and updates configuration
 */

$plugin = 'smart.sleep.manager';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Wrappers.php";

// Configuration file path
$config_file = "/boot/config/plugins/$plugin/$plugin.cfg";

// Get form data
$enabled = $_POST['enabled'] ?? 'false';
$idle_time_minutes = (int)($_POST['idle_time_minutes'] ?? 15);
$sleep_method = $_POST['sleep_method'] ?? 'dynamix_s3';
$array_disks_list = $_POST['array_disks_list'] ?? '';
$ignore_disks_list = $_POST['ignore_disks_list'] ?? '';
$network_monitoring = $_POST['network_monitoring'] ?? 'true';
$network_interface = $_POST['network_interface'] ?? 'eth0';
$network_threshold = (int)($_POST['network_threshold'] ?? 102400);
$telegram_enabled = $_POST['telegram_enabled'] ?? 'false';
$telegram_bot_token = $_POST['telegram_bot_token'] ?? '';
$telegram_chat_id = $_POST['telegram_chat_id'] ?? '';
$telegram_notify_standby = $_POST['telegram_notify_standby'] ?? 'true';
$telegram_notify_sleep = $_POST['telegram_notify_sleep'] ?? 'true';
$telegram_notify_blocked = $_POST['telegram_notify_blocked'] ?? 'false';
$wol_options = $_POST['wol_options'] ?? 'g';
$restart_samba = $_POST['restart_samba'] ?? 'true';
$force_gigabit = $_POST['force_gigabit'] ?? 'false';
$dhcp_renewal = $_POST['dhcp_renewal'] ?? 'false';

// Validate input
if ($idle_time_minutes < 1 || $idle_time_minutes > 1440) {
    $idle_time_minutes = 15;
}

if ($network_threshold < 0) {
    $network_threshold = 102400;
}

// Sanitize telegram inputs
$telegram_bot_token = preg_replace('/[^0-9A-Za-z:_-]/', '', $telegram_bot_token);
$telegram_chat_id = preg_replace('/[^0-9-]/', '', $telegram_chat_id);

// Create configuration content
$config_content = [
    "enabled=\"$enabled\"",
    "idle_time_minutes=\"$idle_time_minutes\"",
    "sleep_method=\"$sleep_method\"",
    "array_disks=\"$array_disks_list\"",
    "ignore_disks=\"$ignore_disks_list\"",
    "network_monitoring=\"$network_monitoring\"",
    "network_interface=\"$network_interface\"",
    "network_threshold=\"$network_threshold\"",
    "telegram_enabled=\"$telegram_enabled\"",
    "telegram_bot_token=\"$telegram_bot_token\"",
    "telegram_chat_id=\"$telegram_chat_id\"",
    "telegram_notify_standby=\"$telegram_notify_standby\"",
    "telegram_notify_sleep=\"$telegram_notify_sleep\"",
    "telegram_notify_blocked=\"$telegram_notify_blocked\"",
    "wol_options=\"$wol_options\"",
    "restart_samba=\"$restart_samba\"",
    "force_gigabit=\"$force_gigabit\"",
    "dhcp_renewal=\"$dhcp_renewal\""
];

// Ensure config directory exists
$config_dir = dirname($config_file);
if (!is_dir($config_dir)) {
    mkdir($config_dir, 0755, true);
}

// Write configuration file
file_put_contents($config_file, implode("\n", $config_content) . "\n");

// Update cron job based on enabled status
$cron_job = "*/5 * * * * /usr/local/emhttp/plugins/$plugin/scripts/smart_sleep.sh >/dev/null 2>&1";

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