<?PHP
/* Telegram Test with cURL (like sleepy.sh) */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST method required']);
    exit;
}

$bot_token = trim($_POST['bot_token'] ?? '');
$chat_id = trim($_POST['chat_id'] ?? '');

if (empty($bot_token) || empty($chat_id)) {
    echo json_encode(['success' => false, 'message' => 'Bot token and chat ID required']);
    exit;
}

// Check if cURL is available
if (!function_exists('curl_init')) {
    echo json_encode(['success' => false, 'message' => 'cURL extension not available']);
    exit;
}

try {
    $hostname = gethostname() ?: 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    $message = "🔧 *Smart Sleep Manager cURL Test*\n🖥️ Server: $hostname\n⏰ Time: $timestamp\n✅ cURL-based Telegram test working!";

    $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
    
    // Initialize cURL (like sleepy.sh)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Smart Sleep Manager/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        echo json_encode(['success' => false, 'message' => 'cURL error: ' . $curl_error]);
        exit;
    }

    if ($http_code !== 200) {
        echo json_encode(['success' => false, 'message' => 'HTTP error: ' . $http_code]);
        exit;
    }

    $telegram_response = json_decode($response, true);
    
    if ($telegram_response && isset($telegram_response['ok']) && $telegram_response['ok']) {
        echo json_encode(['success' => true, 'message' => 'cURL test successful! Message sent to Telegram.']);
    } else {
        $error = isset($telegram_response['description']) ? $telegram_response['description'] : 'Unknown API error';
        echo json_encode(['success' => false, 'message' => 'Telegram API error: ' . $error]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
}
?>