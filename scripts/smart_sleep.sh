#!/bin/bash

# Smart Sleep Manager - Main Sleep Script
# Based on sleepy.sh with plugin configuration integration
# ============================================================================

PLUGIN_NAME="smart.sleep.manager"
CONFIG_FILE="/boot/config/plugins/$PLUGIN_NAME/$PLUGIN_NAME.cfg"
LOG_FILE="/tmp/smart-sleep.log"
ACTIVITY_CHECK_FILE="/tmp/last_activity"
NETWORK_STATE_FILE="/tmp/network_total"

# Global variables for MQTT status reporting
SLEEP_STATUS="unknown"
CURRENT_NETWORK_RATE=0
ACTIVE_DISK_COUNT=0
SLEEP_TIMER_MINUTES=0

# Force sleep flag for manual execution
FORCE_SLEEP=false
if [[ "$1" == "--force-sleep" ]]; then
    FORCE_SLEEP=true
fi

# ============================================================================
# CONFIGURATION LOADING
# ============================================================================

load_config() {
    # Set defaults
    ENABLED="true"
    IDLE_TIME_MINUTES=15
    SLEEP_METHOD="dynamix_s3"
    MONITOR_DISKS=""
    ARRAY_DISKS=""  # Keep for backward compatibility
    NETWORK_MONITORING="true"
    NETWORK_INTERFACE="eth0"
    NETWORK_THRESHOLD_BYTES=102400
    MQTT_ENABLED="false"
    MQTT_HOST=""
    MQTT_PORT="1883"
    MQTT_USERNAME=""
    MQTT_PASSWORD=""
    MQTT_TOPIC_PREFIX="unraid/smart-sleep"
    MQTT_RETAIN="true"
    WOL_OPTIONS="g"
    RESTART_SAMBA="true"
    FORCE_GIGABIT="false"
    DHCP_RENEWAL="false"
    
    # Load from config file if exists
    if [ -f "$CONFIG_FILE" ]; then
        while IFS='=' read -r key value; do
            # Remove quotes and export
            value=$(echo "$value" | sed 's/^"\(.*\)"$/\1/')
            case "$key" in
                enabled) ENABLED="$value" ;;
                idle_time_minutes) IDLE_TIME_MINUTES="$value" ;;
                sleep_method) SLEEP_METHOD="$value" ;;
                monitor_disks) MONITOR_DISKS="$value" ;;
                array_disks) ARRAY_DISKS="$value" ;;  # Backward compatibility
                network_monitoring) NETWORK_MONITORING="$value" ;;
                network_interface) NETWORK_INTERFACE="$value" ;;
                network_threshold) NETWORK_THRESHOLD_BYTES="$value" ;;
                mqtt_enabled) MQTT_ENABLED="$value" ;;
                mqtt_host) MQTT_HOST="$value" ;;
                mqtt_port) MQTT_PORT="$value" ;;
                mqtt_username) MQTT_USERNAME="$value" ;;
                mqtt_password) MQTT_PASSWORD="$value" ;;
                mqtt_topic_prefix) MQTT_TOPIC_PREFIX="$value" ;;
                mqtt_retain) MQTT_RETAIN="$value" ;;
                wol_options) WOL_OPTIONS="$value" ;;
                restart_samba) RESTART_SAMBA="$value" ;;
                force_gigabit) FORCE_GIGABIT="$value" ;;
                dhcp_renewal) DHCP_RENEWAL="$value" ;;
            esac
        done < "$CONFIG_FILE"
    fi
    
    # Auto-detect array disks if not configured
    # Use monitor_disks if available, fall back to array_disks for backward compatibility
    if [ -z "$MONITOR_DISKS" ] && [ -n "$ARRAY_DISKS" ]; then
        MONITOR_DISKS="$ARRAY_DISKS"
    fi
    
    if [ -z "$MONITOR_DISKS" ]; then
        MONITOR_DISKS=$(lsblk -d -n -o NAME,TYPE | grep disk | awk '{print $1}' | tr '\n' ' ')
        log_message "Auto-detected monitor disks: $MONITOR_DISKS"
    else
        log_message "Using configured monitor disks: $MONITOR_DISKS"
    fi
}

# ============================================================================
# LOGGING AND NOTIFICATIONS
# ============================================================================

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

send_mqtt() {
    if [ "$MQTT_ENABLED" = "true" ] && [ -n "$MQTT_HOST" ]; then
        local topic="$1"
        local message="$2"
        local retain_flag=""
        
        if [ "$MQTT_RETAIN" = "true" ]; then
            retain_flag="-r"
        fi
        
        local auth_params=""
        if [ -n "$MQTT_USERNAME" ]; then
            auth_params="-u $MQTT_USERNAME"
            if [ -n "$MQTT_PASSWORD" ]; then
                auth_params="$auth_params -P $MQTT_PASSWORD"
            fi
        fi
        
        # Check if mosquitto_pub is available (host system or Docker)
        if command -v mosquitto_pub >/dev/null 2>&1; then
            # Use host system mosquitto_pub
            mosquitto_pub -h "$MQTT_HOST" -p "$MQTT_PORT" $auth_params $retain_flag -t "$MQTT_TOPIC_PREFIX/$topic" -m "$message" 2>/dev/null
            log_message "MQTT published (host): $MQTT_TOPIC_PREFIX/$topic = $message"
        elif docker ps --format "table {{.Names}}" | grep -q "^mosquitto$" 2>/dev/null; then
            # Use Docker container mosquitto_pub
            docker exec mosquitto mosquitto_pub -h "$MQTT_HOST" -p "$MQTT_PORT" $auth_params $retain_flag -t "$MQTT_TOPIC_PREFIX/$topic" -m "$message" 2>/dev/null
            log_message "MQTT published (docker): $MQTT_TOPIC_PREFIX/$topic = $message"
        else
            log_message "WARNING: mosquitto_pub not available (neither host system nor Docker container 'mosquitto' found)"
        fi
    fi
}

# MQTT Discovery for Home Assistant - creates sensors automatically
publish_mqtt_discovery() {
    if [ "$MQTT_ENABLED" = "true" ] && [ -n "$MQTT_HOST" ]; then
        local hostname=$(hostname)
        local base_topic="homeassistant/sensor/${hostname}_smart_sleep"
        
        # Status sensor
        local status_config="{\"name\":\"${hostname} Sleep Status\",\"state_topic\":\"${MQTT_TOPIC_PREFIX}/status\",\"unique_id\":\"${hostname}_sleep_status\",\"device\":{\"identifiers\":[\"${hostname}_smart_sleep\"],\"name\":\"${hostname} Smart Sleep\",\"manufacturer\":\"SkyCryer\",\"model\":\"Smart Sleep Manager\"}}"
        send_mqtt "$(echo "$base_topic/status/config" | tr '[:upper:]' '[:lower:]')" "$status_config"
        
        # Uptime sensor  
        local uptime_config="{\"name\":\"${hostname} Uptime\",\"state_topic\":\"${MQTT_TOPIC_PREFIX}/uptime\",\"unique_id\":\"${hostname}_uptime\",\"unit_of_measurement\":\"s\",\"device_class\":\"duration\",\"device\":{\"identifiers\":[\"${hostname}_smart_sleep\"],\"name\":\"${hostname} Smart Sleep\",\"manufacturer\":\"SkyCryer\",\"model\":\"Smart Sleep Manager\"}}"
        send_mqtt "$(echo "$base_topic/uptime/config" | tr '[:upper:]' '[:lower:]')" "$uptime_config"
        
        # Network rate sensor
        local network_config="{\"name\":\"${hostname} Network Rate\",\"state_topic\":\"${MQTT_TOPIC_PREFIX}/network_rate\",\"unique_id\":\"${hostname}_network_rate\",\"unit_of_measurement\":\"B/s\",\"device_class\":\"data_rate\",\"device\":{\"identifiers\":[\"${hostname}_smart_sleep\"],\"name\":\"${hostname} Smart Sleep\",\"manufacturer\":\"SkyCryer\",\"model\":\"Smart Sleep Manager\"}}"
        send_mqtt "$(echo "$base_topic/network_rate/config" | tr '[:upper:]' '[:lower:]')" "$network_config"
        
        # Active disks sensor
        local disks_config="{\"name\":\"${hostname} Active Disks\",\"state_topic\":\"${MQTT_TOPIC_PREFIX}/active_disks\",\"unique_id\":\"${hostname}_active_disks\",\"unit_of_measurement\":\"disks\",\"device\":{\"identifiers\":[\"${hostname}_smart_sleep\"],\"name\":\"${hostname} Smart Sleep\",\"manufacturer\":\"SkyCryer\",\"model\":\"Smart Sleep Manager\"}}"
        send_mqtt "$(echo "$base_topic/active_disks/config" | tr '[:upper:]' '[:lower:]')" "$disks_config"
        
        # Sleep timer sensor
        local timer_config="{\"name\":\"${hostname} Sleep Timer\",\"state_topic\":\"${MQTT_TOPIC_PREFIX}/sleep_timer\",\"unique_id\":\"${hostname}_sleep_timer\",\"unit_of_measurement\":\"min\",\"device_class\":\"duration\",\"device\":{\"identifiers\":[\"${hostname}_smart_sleep\"],\"name\":\"${hostname} Smart Sleep\",\"manufacturer\":\"SkyCryer\",\"model\":\"Smart Sleep Manager\"}}"
        send_mqtt "$(echo "$base_topic/sleep_timer/config" | tr '[:upper:]' '[:lower:]')" "$timer_config"
        
        log_message "MQTT Discovery: Published sensor configurations for Home Assistant"
    fi
}

publish_mqtt_sensors() {
    if [ "$MQTT_ENABLED" = "true" ] && [ -n "$MQTT_HOST" ]; then
        local hostname=$(hostname)
        local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
        local uptime_seconds=$(awk '{print int($1)}' /proc/uptime 2>/dev/null || echo "0")
        
        # Publish individual sensors
        send_mqtt "hostname" "$hostname"
        send_mqtt "uptime" "$uptime_seconds"
        send_mqtt "network_rate" "${CURRENT_NETWORK_RATE:-0}"
        send_mqtt "active_disks" "${ACTIVE_DISK_COUNT:-0}"
        send_mqtt "sleep_timer" "${SLEEP_TIMER_MINUTES:-0}"
        send_mqtt "last_check" "$timestamp"
        
        # Publish combined status JSON for Home Assistant
        local status_json="{\"hostname\":\"$hostname\",\"uptime\":$uptime_seconds,\"network_rate\":${CURRENT_NETWORK_RATE:-0},\"active_disks\":${ACTIVE_DISK_COUNT:-0},\"sleep_timer\":${SLEEP_TIMER_MINUTES:-0},\"status\":\"$SLEEP_STATUS\",\"last_check\":\"$timestamp\"}"
        send_mqtt "state" "$status_json"
        
        log_message "MQTT sensors published: status=$SLEEP_STATUS, uptime=${uptime_seconds}s, network=${CURRENT_NETWORK_RATE:-0}B/s, active_disks=${ACTIVE_DISK_COUNT:-0}"
    fi
}

# ============================================================================
# SLEEP FUNCTIONS
# ============================================================================

s3_pre_sleep_activity() {
    log_message "=== Pre-Sleep Activities ==="
    echo "🔧 Preparing for sleep..."
    
    # Configure Wake-on-LAN
    if [ -n "$WOL_OPTIONS" ] && command -v ethtool >/dev/null 2>&1; then
        log_message "Configuring Wake-on-LAN on $NETWORK_INTERFACE: $WOL_OPTIONS"
        echo "🌐 Configuring Wake-on-LAN: $WOL_OPTIONS"
        ethtool -s "$NETWORK_INTERFACE" wol "$WOL_OPTIONS" 2>/dev/null || {
            log_message "WARNING: Wake-on-LAN configuration failed"
            echo "⚠️ WARNING: Wake-on-LAN configuration failed"
        }
    fi
    
    # Filesystem sync
    log_message "Syncing filesystems before sleep..."
    echo "💾 Syncing filesystems..."
    sync
    sleep 1
    
    log_message "Pre-sleep preparation completed"
    echo "✅ Sleep preparation completed"
}

s3_post_wake_activity() {
    log_message "=== Post-Wake Activities ==="
    echo "🌅 System awake!"
    
    # Force gigabit speed if enabled
    if [ "$FORCE_GIGABIT" = "true" ] && command -v ethtool >/dev/null 2>&1; then
        log_message "Forcing gigabit speed on $NETWORK_INTERFACE"
        echo "🌐 Forcing gigabit speed..."
        ethtool -s "$NETWORK_INTERFACE" speed 1000 2>/dev/null || {
            log_message "WARNING: Gigabit forcing failed"
        }
        sleep 2
    fi
    
    # DHCP renewal if enabled
    if [ "$DHCP_RENEWAL" = "true" ]; then
        log_message "Renewing DHCP lease"
        echo "🔄 Renewing DHCP lease..."
        if command -v dhcpcd >/dev/null 2>&1; then
            dhcpcd -n 2>/dev/null || log_message "DHCP renewal failed"
        elif command -v dhclient >/dev/null 2>&1; then
            dhclient -r "$NETWORK_INTERFACE" 2>/dev/null
            sleep 1
            dhclient "$NETWORK_INTERFACE" 2>/dev/null || log_message "DHCP renewal failed"
        fi
        sleep 3
    fi
    
    # Restart Samba if enabled
    if [ "$RESTART_SAMBA" = "true" ]; then
        log_message "Restarting Samba after wake-up..."
        echo "🔄 Restarting Samba (important for SMB shares)..."
        
        if [ -x "/etc/rc.d/rc.samba" ]; then
            /etc/rc.d/rc.samba restart 2>/dev/null && {
                log_message "Samba restarted successfully"
                echo "✅ Samba restarted successfully"
            } || {
                log_message "WARNING: Samba restart failed"
                echo "⚠️ WARNING: Samba restart failed"
            }
            sleep 2
        else
            log_message "WARNING: /etc/rc.d/rc.samba not found"
            echo "⚠️ WARNING: /etc/rc.d/rc.samba not found"
        fi
    fi
    
    log_message "All post-wake activities completed"
    echo "✅ All post-wake activities completed"
}

execute_sleep() {
    case "$SLEEP_METHOD" in
        "dynamix_s3")
            log_message "=== Executing Dynamix S3 Sleep ==="
            echo "🌙 Using Dynamix S3 Sleep method..."
            
            s3_pre_sleep_activity
            
            log_message "Entering S3 sleep mode: echo -n mem > /sys/power/state"
            echo "🌙 Entering S3 sleep mode..."
            echo ""
            
            echo -n mem > /sys/power/state
            
            echo ""
            log_message "System awoke from S3 sleep"
            echo "🌅 System awoke!"
            
            s3_post_wake_activity
            ;;
        "systemctl_suspend")
            log_message "=== Executing systemctl suspend ==="
            echo "🌙 Using systemctl suspend..."
            
            s3_pre_sleep_activity
            systemctl suspend
            s3_post_wake_activity
            ;;
        *)
            log_message "ERROR: Unknown sleep method: $SLEEP_METHOD"
            echo "❌ ERROR: Unknown sleep method: $SLEEP_METHOD"
            return 1
            ;;
    esac
    
    log_message "Sleep cycle completed"
}

# ============================================================================
# MONITORING FUNCTIONS
# ============================================================================

check_array_status() {
    if [ ! -f /var/local/emhttp/var.ini ]; then
        log_message "ERROR: Array status file not found"
        return 1
    fi
    
    local array_state=$(grep -E '^mdState=' /var/local/emhttp/var.ini | cut -d'=' -f2 | tr -d '"')
    
    if [ "$array_state" != "STARTED" ]; then
        log_message "Array is not started (Status: $array_state)"
        return 1
    fi
    
    return 0
}

check_array_activity() {
    local active_disks=()
    
    log_message "=== Monitor Disk Standby Check ==="
    
    for disk_name in $MONITOR_DISKS; do
        
        # Check if disk device exists
        if [ ! -b "/dev/$disk_name" ]; then
            log_message "WARNING: Disk /dev/$disk_name not found, skipping"
            continue
        fi
        
        # Check standby status
        local standby_status=$(hdparm -C /dev/$disk_name 2>/dev/null | grep "drive state")
        
        if [[ "$standby_status" =~ "standby" ]] || [[ "$standby_status" =~ "sleeping" ]]; then
            log_message "$disk_name: IN STANDBY"
        else
            echo "DISK ACTIVE: $disk_name is NOT in standby!"
            log_message "DISK ACTIVE: $disk_name is NOT in standby ($standby_status)"
            active_disks+=("$disk_name")
        fi
    done
    
    # Set global variable for MQTT
    ACTIVE_DISK_COUNT=${#active_disks[@]}
    
    if [ ${#active_disks[@]} -gt 0 ]; then
        local active_list=$(IFS=', '; echo "${active_disks[*]}")
        log_message "ACTIVE ARRAY DISKS (not in standby): $active_list"
        echo "ACTIVE ARRAY DISKS (not in standby): $active_list"
        
        # Reset timer
        echo "$(date)" > "$ACTIVITY_CHECK_FILE"
        log_message "Array activity detected - sleep skipped"
        
        SLEEP_STATUS="blocked_disks"
        
        echo ""
        echo "💤 SLEEP BLOCKED: Active array disks present"
        echo ""
        return 1
    else
        log_message "All array disks are in standby/spindown"
        echo "All array disks are in standby/spindown ✓"
        return 0
    fi
}

check_network_activity() {
    if [ "$NETWORK_MONITORING" != "true" ]; then
        log_message "Network monitoring disabled"
        return 1  # Low activity (monitoring disabled)
    fi
    
    local has_network_activity=false
    CURRENT_NETWORK_RATE=0
    
    # Collect network statistics
    local total_rx=0
    local total_tx=0
    
    while IFS= read -r line; do
        if [[ "$line" =~ ^[[:space:]]*([^[:space:]]+):[[:space:]]*([0-9]+)[[:space:]]+[0-9]+[[:space:]]+[0-9]+[[:space:]]+[0-9]+[[:space:]]+[0-9]+[[:space:]]+[0-9]+[[:space:]]+[0-9]+[[:space:]]+[0-9]+[[:space:]]+([0-9]+) ]]; then
            local interface="${BASH_REMATCH[1]}"
            local rx_bytes="${BASH_REMATCH[2]}"
            local tx_bytes="${BASH_REMATCH[3]}"
            
            # Skip loopback
            if [ "$interface" != "lo" ]; then
                total_rx=$((total_rx + rx_bytes))
                total_tx=$((total_tx + tx_bytes))
            fi
        fi
    done < /proc/net/dev
    
    local total_network=$((total_rx + total_tx))
    
    if [ -f "$NETWORK_STATE_FILE" ]; then
        local prev_network=$(cat "$NETWORK_STATE_FILE")
        local network_diff=$((total_network - prev_network))
        
        # Calculate bytes per second (5-minute cron interval)
        local cron_interval_seconds=300
        local network_rate_per_sec=$((network_diff / cron_interval_seconds))
        CURRENT_NETWORK_RATE=$network_rate_per_sec
        
        log_message "Network traffic: ${network_rate_per_sec} Bytes/s (Threshold: ${NETWORK_THRESHOLD_BYTES} Bytes/s)"
        echo "Network traffic: ${network_rate_per_sec} Bytes/s (Threshold: ${NETWORK_THRESHOLD_BYTES} Bytes/s)"
        
        if [ "$network_rate_per_sec" -gt "$NETWORK_THRESHOLD_BYTES" ]; then
            log_message "Network activity above threshold: ${network_rate_per_sec} > ${NETWORK_THRESHOLD_BYTES} Bytes/s"
            echo "Network activity above threshold: ${network_rate_per_sec} > ${NETWORK_THRESHOLD_BYTES} Bytes/s"
            has_network_activity=true
            echo "$(date)" > "$ACTIVITY_CHECK_FILE"
            SLEEP_STATUS="blocked_network"
        fi
    else
        log_message "First network measurement - baseline created"
        echo "First network measurement - baseline created"
        CURRENT_NETWORK_RATE="N/A (first measurement)"
    fi
    
    echo "$total_network" > "$NETWORK_STATE_FILE"
    
    if [ "$has_network_activity" = true ]; then
        return 0  # High network activity
    else
        return 1  # Low network activity
    fi
}

# ============================================================================
# MAIN FUNCTION
# ============================================================================

main() {
    # Load configuration
    load_config
    
    # Check if plugin is enabled (unless force sleep)
    if [ "$ENABLED" != "true" ] && [ "$FORCE_SLEEP" != true ]; then
        log_message "Smart Sleep Manager is disabled"
        SLEEP_STATUS="disabled"
        publish_mqtt_sensors
        exit 0
    fi
    
    log_message "=== Smart Sleep Manager Check Started ==="
    echo "=== Smart Sleep Manager Check Started ==="
    
    # Publish MQTT Discovery (only once per hour to avoid spam)
    local current_hour=$(date +%H)
    local last_discovery_file="/tmp/smart_sleep_discovery_hour"
    if [ ! -f "$last_discovery_file" ] || [ "$(cat "$last_discovery_file" 2>/dev/null)" != "$current_hour" ]; then
        publish_mqtt_discovery
        echo "$current_hour" > "$last_discovery_file"
    fi
    
    # Force sleep if requested
    if [ "$FORCE_SLEEP" = true ]; then
        log_message "Force sleep requested - executing immediately"
        echo "🌙 FORCE SLEEP: Executing sleep immediately"
        
        SLEEP_STATUS="force_sleep"
        publish_mqtt_sensors
        
        if [ "$TELEGRAM_NOTIFY_SLEEP" = "true" ]; then
            send_telegram "🌙 *Forced Sleep Activated!*
🔧 Initiated manually
🚀 WOL wake-up available as usual"
        fi
        
        execute_sleep
        exit 0
    fi
    
    # Check array status
    if ! check_array_status; then
        log_message "Array is not ready - sleep skipped"
        echo "Array is not ready - sleep skipped"
        SLEEP_STATUS="array_not_ready"
        publish_mqtt_sensors
        exit 0
    fi
    
    echo "Array Status: OK - Array is started"
    log_message "Array Status: OK - Array is started"
    
    # Check array disk activity
    if ! check_array_activity; then
        # Publish MQTT with current status (blocked_disks was set in check_array_activity)
        publish_mqtt_sensors
        exit 0  # Active disks found, exit
    fi
    
    # Check network activity
    if check_network_activity; then
        log_message "High network activity detected - sleep skipped"
        echo ""
        echo "💤 SLEEP BLOCKED: Network traffic too high"
        echo ""
        # Publish MQTT with current status (blocked_network was set in check_network_activity)
        publish_mqtt_sensors
        exit 0
    fi
    
    echo "Network activity under threshold ✓"
    log_message "Network activity under threshold"
    
    # Check timer
    if [ -f "$ACTIVITY_CHECK_FILE" ]; then
        local last_activity=$(date -d "$(cat "$ACTIVITY_CHECK_FILE")" +%s 2>/dev/null || echo 0)
        local current_time=$(date +%s)
        local standby_minutes=$(( (current_time - last_activity) / 60 ))
        
        log_message "All conditions met for $standby_minutes minutes"
        
        # Set timer for MQTT
        SLEEP_TIMER_MINUTES=$((IDLE_TIME_MINUTES - standby_minutes))
        if [ "$SLEEP_TIMER_MINUTES" -lt 0 ]; then
            SLEEP_TIMER_MINUTES=0
        fi
        
        if [ "$standby_minutes" -ge "$IDLE_TIME_MINUTES" ]; then
            log_message "System going to sleep (all conditions met for $standby_minutes minutes)"
            
            SLEEP_STATUS="sleeping"
            publish_mqtt_sensors
            
            if [ "$TELEGRAM_NOTIFY_SLEEP" = "true" ]; then
                send_telegram "🌙 *Server going to sleep!*
✅ All array disks in standby for $standby_minutes minutes
💤 Method: $SLEEP_METHOD
🔄 Samba will restart after WOL automatically
🚀 WOL wake-up available as usual!"
            fi
            
            echo ""
            echo "🌙 SLEEP ACTIVATED: System going to sleep NOW! 🌙"
            echo "   Reason: All conditions met for $standby_minutes minutes (minimum: $IDLE_TIME_MINUTES)"
            echo ""
            
            execute_sleep
            
            # Post wake-up status
            SLEEP_STATUS="awake"
            publish_mqtt_sensors
            
            log_message "Sleep cycle completed, system awake"
        else
            SLEEP_STATUS="waiting"
            publish_mqtt_sensors
            
            if [ "$TELEGRAM_NOTIFY_STANDBY" = "true" ]; then
                send_telegram "⏳ *Timer continues*
✅ All array disks in standby for $standby_minutes minutes
🌐 Network traffic: $CURRENT_NETWORK_RATE Bytes/s
⏰ $((IDLE_TIME_MINUTES - standby_minutes)) minutes until sleep"
            fi
            
            echo ""
            echo "⏳ WAITING: $((IDLE_TIME_MINUTES - standby_minutes)) minutes until sleep possible"
            echo "   All conditions met for: $standby_minutes minutes (minimum: $IDLE_TIME_MINUTES)"
            echo ""
        fi
    else
        echo "$(date)" > "$ACTIVITY_CHECK_FILE"
        log_message "First execution - standby timer started"
        
        SLEEP_STATUS="timer_started"
        SLEEP_TIMER_MINUTES=$IDLE_TIME_MINUTES
        publish_mqtt_sensors
        
        if [ "$TELEGRAM_NOTIFY_STANDBY" = "true" ]; then
            send_telegram "⏳ *Standby timer started*
✅ All array disks are in standby
🌐 Network traffic low ($CURRENT_NETWORK_RATE Bytes/s)
⏰ Sleep possible in $IDLE_TIME_MINUTES minutes"
        fi
        
        echo ""
        echo "🔄 FIRST EXECUTION: Standby timer started"
        echo "   Sleep possible in $IDLE_TIME_MINUTES minutes (if all conditions remain met)"
        echo ""
    fi
    
    log_message "=== Smart Sleep Manager Check Completed ==="
}

# ============================================================================
# SCRIPT EXECUTION
# ============================================================================

main "$@"