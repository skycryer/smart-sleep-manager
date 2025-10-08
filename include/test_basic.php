<?PHP
/* Basic PHP Test for Smart Sleep Manager */
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'message' => 'PHP is working correctly',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion()
]);
?>