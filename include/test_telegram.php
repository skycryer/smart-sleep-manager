<?PHP
/* Smart Sleep Manager - Telegram Test Handler
 * Tests Telegram bot configuration
 */

// Start output buffering and prevent any unintended output
ob_start();

// Set proper headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Function to send clean JSON response
function sendResponse($success, $message) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    $response = array(
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    );
    
    echo json_encode($response);
    
    // Flush and end output buffering
    if (ob_get_length()) ob_end_flush();
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'POST method required');
}

// Get POST data
$bot_token = isset($_POST['bot_token']) ? trim($_POST['bot_token']) : '';
$chat_id = isset($_POST['chat_id']) ? trim($_POST['chat_id']) : '';

// Validate inputs
if (empty($bot_token) || empty($chat_id)) {
    sendResponse(false, 'Bot token and chat ID are required');
}

// Basic format validation
if (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $bot_token)) {
    sendResponse(false, 'Invalid bot token format');
}

if (!preg_match('/^-?\d+$/', $chat_id)) {
    sendResponse(false, 'Invalid chat ID format');
}

try {
    // Prepare test message
    $hostname = 'Unknown';
    if (function_exists('gethostname')) {
        $hostname = gethostname();
    } elseif (isset($_SERVER['SERVER_NAME'])) {
        $hostname = $_SERVER['SERVER_NAME'];
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $message = "🔧 *Smart Sleep Manager Test*\n🖥️ Server: " . $hostname . "\n⏰ Time: " . $timestamp . "\n✅ Telegram configuration is working correctly!";

    // Prepare Telegram API request
    $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
    
    $post_data = http_build_query(array(
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ));

    // Create context for the request
    $context = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                       "Content-Length: " . strlen($post_data) . "\r\n",
            'content' => $post_data,
            'timeout' => 30
        )
    ));

    // Make the request
    $result = file_get_contents($url, false, $context);

    if ($result === false) {
        sendResponse(false, 'Failed to connect to Telegram API');
    }

    // Parse the response
    $telegram_response = json_decode($result, true);

    if ($telegram_response === null) {
        sendResponse(false, 'Invalid response from Telegram API');
    }

    if (isset($telegram_response['ok']) && $telegram_response['ok'] === true) {
        sendResponse(true, 'Test message sent successfully to Telegram!');
    } else {
        $error_msg = 'Unknown error';
        if (isset($telegram_response['description'])) {
            $error_msg = $telegram_response['description'];
        }
        if (isset($telegram_response['error_code'])) {
            $error_msg = 'Error ' . $telegram_response['error_code'] . ': ' . $error_msg;
        }
        sendResponse(false, $error_msg);
    }

} catch (Exception $e) {
    sendResponse(false, 'Exception: ' . $e->getMessage());
}

// This should never be reached, but just in case
sendResponse(false, 'Unknown error occurred');
?>