<?PHP
/* Smart Sleep Manager - Telegram Test Handler
 * Tests Telegram bot configuration
 */

// Ensure clean output
ob_clean();
header('Content-Type: application/json');

try {
    $bot_token = $_POST['bot_token'] ?? '';
    $chat_id = $_POST['chat_id'] ?? '';

    if (empty($bot_token) || empty($chat_id)) {
        echo json_encode(['success' => false, 'error' => 'Bot token and chat ID are required']);
        exit;
    }

    // Sanitize inputs
    $bot_token = preg_replace('/[^0-9A-Za-z:_-]/', '', $bot_token);
    $chat_id = preg_replace('/[^0-9-]/', '', $chat_id);

    if (empty($bot_token) || empty($chat_id)) {
        echo json_encode(['success' => false, 'error' => 'Invalid bot token or chat ID format']);
        exit;
    }

    // Prepare test message
    $hostname = exec('hostname') ?: 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    $message = "🔧 *Smart Sleep Manager Test*\n🖥️ Server: $hostname\n⏰ Time: $timestamp\n✅ Telegram configuration is working correctly!";

    // Send test message
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ]);

    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        echo json_encode(['success' => false, 'error' => 'Network error - could not reach Telegram API. Check internet connection.']);
        exit;
    }

    $response = json_decode($result, true);

    if ($response && isset($response['ok']) && $response['ok']) {
        echo json_encode(['success' => true, 'message' => 'Test message sent successfully']);
    } else {
        $error = isset($response['description']) ? $response['description'] : 'Unknown API error';
        if (isset($response['error_code'])) {
            $error = "API Error {$response['error_code']}: $error";
        }
        echo json_encode(['success' => false, 'error' => $error]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'PHP Error: ' . $e->getMessage()]);
}
?>