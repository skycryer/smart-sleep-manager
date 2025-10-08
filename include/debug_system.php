<?PHP
/* Smart Sleep Manager - Comprehensive Debug Tool
 * Gathers system information to diagnose Unraid issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure clean output and proper headers
if (ob_get_length()) ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    $debug_info = [];
    
    // Basic PHP info
    $debug_info['php_version'] = phpversion();
    $debug_info['timestamp'] = date('Y-m-d H:i:s');
    $debug_info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $debug_info['document_root'] = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';
    
    // Check current working directory
    $debug_info['current_directory'] = getcwd();
    $debug_info['script_filename'] = $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown';
    $debug_info['request_uri'] = $_SERVER['REQUEST_URI'] ?? 'Unknown';
    
    // Check plugin directory structure
    $plugin_dir = '/usr/local/emhttp/plugins/smart.sleep.manager';
    $debug_info['plugin_dir_exists'] = is_dir($plugin_dir);
    
    if ($debug_info['plugin_dir_exists']) {
        $debug_info['plugin_dir_contents'] = [];
        $items = scandir($plugin_dir);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..') {
                $path = $plugin_dir . '/' . $item;
                $debug_info['plugin_dir_contents'][$item] = [
                    'type' => is_dir($path) ? 'directory' : 'file',
                    'readable' => is_readable($path),
                    'writable' => is_writable($path),
                    'size' => is_file($path) ? filesize($path) : null
                ];
            }
        }
    }
    
    // Check include directory
    $include_dir = $plugin_dir . '/include';
    $debug_info['include_dir_exists'] = is_dir($include_dir);
    
    if ($debug_info['include_dir_exists']) {
        $debug_info['include_dir_contents'] = [];
        $items = scandir($include_dir);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..') {
                $path = $include_dir . '/' . $item;
                $debug_info['include_dir_contents'][$item] = [
                    'type' => is_file($path) ? 'file' : 'other',
                    'readable' => is_readable($path),
                    'executable' => is_executable($path),
                    'size' => filesize($path),
                    'permissions' => substr(sprintf('%o', fileperms($path)), -4)
                ];
            }
        }
    }
    
    // Check specific files
    $files_to_check = [
        'test_telegram.php',
        'test_basic.php',
        'update.sleep.php',
        'SleepMode.php'
    ];
    
    $debug_info['file_status'] = [];
    foreach ($files_to_check as $file) {
        $path = $include_dir . '/' . $file;
        $debug_info['file_status'][$file] = [
            'exists' => file_exists($path),
            'readable' => is_readable($path),
            'size' => file_exists($path) ? filesize($path) : 0,
            'permissions' => file_exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : 'N/A',
            'first_10_chars' => file_exists($path) && is_readable($path) ? substr(file_get_contents($path), 0, 10) : 'N/A'
        ];
    }
    
    // Check PHP configuration
    $debug_info['php_config'] = [
        'allow_url_fopen' => ini_get('allow_url_fopen') ? 'yes' : 'no',
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'error_reporting' => error_reporting(),
        'display_errors' => ini_get('display_errors')
    ];
    
    // Check loaded extensions
    $debug_info['php_extensions'] = get_loaded_extensions();
    
    // Check environment
    $debug_info['environment'] = [
        'user' => get_current_user(),
        'hostname' => trim(exec('hostname 2>/dev/null')) ?: 'Unknown',
        'uname' => trim(exec('uname -a 2>/dev/null')) ?: 'Unknown'
    ];
    
    // Check web server access
    $debug_info['web_access'] = [
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ];
    
    // Test file_get_contents
    $debug_info['network_test'] = [];
    try {
        $test_url = 'https://httpbin.org/get';
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $result = @file_get_contents($test_url, false, $context);
        $debug_info['network_test']['httpbin_test'] = $result !== false ? 'success' : 'failed';
    } catch (Exception $e) {
        $debug_info['network_test']['httpbin_test'] = 'error: ' . $e->getMessage();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Debug information collected successfully',
        'debug_info' => $debug_info
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Debug script error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Debug script fatal error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// Ensure output is flushed
if (ob_get_length()) ob_end_flush();
?>