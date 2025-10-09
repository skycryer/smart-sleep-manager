<?PHP
// Simple test to see if we can generate JSON output at all
header('Content-Type: application/json');
echo json_encode(array('test' => 'simple', 'time' => date('Y-m-d H:i:s')));
?>