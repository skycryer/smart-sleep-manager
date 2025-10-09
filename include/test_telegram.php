<?PHP
/* Smart Sleep Manager - Telegram Test Handler
 * Tests Telegram bot configuration
 */

// Clean output buffer and set headers
ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

function sendJsonResponse($data) {
    ob_clean();
    echo json_encode($data);
    ob_end_flush();
    exit;
}

// Check request method and data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'error' => 'POST method required']);
}

$bot_token = $_POST['bot_token'] ?? '';
$chat_id = $_POST['chat_id'] ?? '';

if (empty($bot_token) || empty($chat_id)) {
    sendJsonResponse(['success' => false, 'error' => 'Bot token and chat ID are required']);
}

// Sanitize inputs
$bot_token_clean = preg_replace('/[^0-9A-Za-z:_-]/', '', $bot_token);
$chat_id_clean = preg_replace('/[^0-9-]/', '', $chat_id);

if (empty($bot_token_clean) || empty($chat_id_clean)) {
    sendJsonResponse(['success' => false, 'error' => 'Invalid bot token or chat ID format']);
}

try {
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
        sendJsonResponse(['success' => false, 'error' => 'Network error: Could not connect to Telegram API']);
    }

    $response = json_decode($result, true);

    if ($response && isset($response['ok']) && $response['ok']) {
        sendJsonResponse(['success' => true, 'message' => 'Test message sent successfully']);
    } else {
        $error = isset($response['description']) ? $response['description'] : 'Unknown API error';
        if (isset($response['error_code'])) {
            $error = "API Error {$response['error_code']}: $error";
        }
        sendJsonResponse(['success' => false, 'error' => $error]);
    }

} catch (Exception $e) {
    sendJsonResponse(['success' => false, 'error' => 'PHP Error: ' . $e->getMessage()]);
}
?>