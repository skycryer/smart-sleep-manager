<?PHP
/* Smart Sleep Manager - Sleep Mode Handler
 * Executes the actual sleep process
 */

$plugin = 'smart.sleep.manager';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Wrappers.php";

// Load configuration
$cfg = parse_plugin_cfg($plugin);

// Log sleep initiation
$log_message = date('Y-m-d H:i:s') . " - Manual sleep initiated from web interface\n";
file_put_contents('/tmp/smart-sleep.log', $log_message, FILE_APPEND | LOCK_EX);

// Send Telegram notification if enabled
if ($cfg['telegram_enabled'] === 'true' && !empty($cfg['telegram_bot_token']) && !empty($cfg['telegram_chat_id'])) {
    $hostname = exec('hostname');
    $timestamp = date('Y-m-d H:i:s');
    $message = "🌙 *Manual Sleep Initiated*\n🖥️ Server: $hostname\n⏰ Time: $timestamp\n🔄 Method: Web Interface";
    
    $url = "https://api.telegram.org/bot{$cfg['telegram_bot_token']}/sendMessage";
    $data = [
        'chat_id' => $cfg['telegram_chat_id'],
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        ]
    ]);
    
    file_get_contents($url, false, $context);
}

// Execute sleep script
exec("/usr/local/emhttp/plugins/$plugin/scripts/smart_sleep.sh --force-sleep 2>&1", $output, $return_code);

// Log the output
foreach ($output as $line) {
    $log_message = date('Y-m-d H:i:s') . " - $line\n";
    file_put_contents('/tmp/smart-sleep.log', $log_message, FILE_APPEND | LOCK_EX);
}

echo "Sleep process executed. Check log for details.";
?>