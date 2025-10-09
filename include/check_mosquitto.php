<?PHP
/* Smart Sleep Manager - Check Mosquitto Availability
 * Returns 'available' or 'missing' based on mosquitto_pub availability
 */

header('Content-Type: text/plain');

// Check if mosquitto_pub command is available on host system
$output = null;
$return_var = null;
exec('command -v mosquitto_pub 2>/dev/null', $output, $return_var);

if ($return_var === 0 && !empty($output)) {
    echo 'available';
} else {
    // Check if mosquitto Docker container is available
    $docker_output = null;
    $docker_return = null;
    exec('docker ps --format "table {{.Names}}" 2>/dev/null | grep -q "^mosquitto$"', $docker_output, $docker_return);
    
    if ($docker_return === 0) {
        // Test if mosquitto_pub works in the container
        $test_output = null;
        $test_return = null;
        exec('docker exec mosquitto mosquitto_pub --help 2>/dev/null', $test_output, $test_return);
        
        if ($test_return === 0) {
            echo 'available';
        } else {
            echo 'missing';
        }
    } else {
        echo 'missing';
    }
}
?>