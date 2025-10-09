<?PHP
/* Minimal Telegram Test - Just check if we can receive POST data */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bot_token = $_POST['bot_token'] ?? 'not_received';
    $chat_id = $_POST['chat_id'] ?? 'not_received';
    
    echo json_encode(array(
        'success' => true,
        'message' => 'POST data received successfully',
        'received_bot_token' => substr($bot_token, 0, 10) . '...',
        'received_chat_id' => $chat_id,
        'timestamp' => date('Y-m-d H:i:s')
    ));
} else {
    echo json_encode(array(
        'success' => false,
        'message' => 'This script requires POST method',
        'received_method' => $_SERVER['REQUEST_METHOD'],
        'timestamp' => date('Y-m-d H:i:s')
    ));
}
?>