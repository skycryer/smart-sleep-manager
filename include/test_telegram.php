<?PHP
/* Smart Sleep Manager - Telegram Test Handler
 * Tests Telegram bot configuration
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure clean output and proper headers
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Log that we're starting
error_log("Smart Sleep Manager: Telegram test started");

try {
    // Check if we even got here
    if (!isset($_POST)) {
        echo json_encode(['success' => false, 'error' => 'No POST data received']);
        exit;
    }

    $bot_token = $_POST['bot_token'] ?? '';
    $chat_id = $_POST['chat_id'] ?? '';

    error_log("Smart Sleep Manager: Received bot_token=" . substr($bot_token, 0, 10) . "..., chat_id=$chat_id");

    if (empty($bot_token) || empty($chat_id)) {
        echo json_encode(['success' => false, 'error' => 'Bot token and chat ID are required']);
        exit;
    }

    // Sanitize inputs
    $bot_token_clean = preg_replace('/[^0-9A-Za-z:_-]/', '', $bot_token);
    $chat_id_clean = preg_replace('/[^0-9-]/', '', $chat_id);

    if (empty($bot_token_clean) || empty($chat_id_clean)) {
        echo json_encode(['success' => false, 'error' => 'Invalid bot token or chat ID format']);
        exit;
    }

    // Prepare test message
    $hostname = trim(exec('hostname 2>/dev/null')) ?: 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    $message = "🔧 *Smart Sleep Manager Test*\n🖥️ Server: $hostname\n⏰ Time: $timestamp\n✅ Telegram configuration is working correctly!";

    // Send test message
    $url = "https://api.telegram.org/bot$bot_token_clean/sendMessage";
    $data = [
        'chat_id' => $chat_id_clean,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    error_log("Smart Sleep Manager: Sending to URL: $url");

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 15
        ]
    ]);

    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        $error = error_get_last();
        $error_msg = $error ? $error['message'] : 'Unknown network error';
        error_log("Smart Sleep Manager: Network error: $error_msg");
        echo json_encode(['success' => false, 'error' => "Network error: $error_msg"]);
        exit;
    }

    error_log("Smart Sleep Manager: Telegram API response: $result");

    $response = json_decode($result, true);

    if ($response && isset($response['ok']) && $response['ok']) {
        echo json_encode(['success' => true, 'message' => 'Test message sent successfully']);
    } else {
        $error = isset($response['description']) ? $response['description'] : 'Unknown API error';
        if (isset($response['error_code'])) {
            $error = "API Error {$response['error_code']}: $error";
        }
        error_log("Smart Sleep Manager: Telegram API error: $error");
        echo json_encode(['success' => false, 'error' => $error]);
    }

} catch (Exception $e) {
    error_log("Smart Sleep Manager: Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'PHP Error: ' . $e->getMessage()]);
} catch (Error $e) {
    error_log("Smart Sleep Manager: Fatal error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'PHP Fatal Error: ' . $e->getMessage()]);
}

// Ensure output is flushed
if (ob_get_length()) ob_end_flush();
?>