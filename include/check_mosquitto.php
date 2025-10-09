<?PHP
/* Smart Sleep Manager - Check Mosquitto Availability
 * Returns 'available' or 'missing' based on mosquitto_pub availability
 */

header('Content-Type: text/plain');

// Check if mosquitto_pub command is available
$output = null;
$return_var = null;
exec('command -v mosquitto_pub 2>/dev/null', $output, $return_var);

if ($return_var === 0 && !empty($output)) {
    echo 'available';
} else {
    echo 'missing';
}
?>