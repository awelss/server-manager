#!/usr/bin/env bash
set -euo pipefail

CONFIG_FILE="/etc/vps-agent/config.json"
LOG_BUFFER="/tmp/vps-agent-log-buffer.json"

# --- Config Parsing (no jq dependency) ---
parse_config_value() {
    grep -o "\"$1\"\s*:\s*\"[^\"]*\"" "$CONFIG_FILE" | sed 's/.*: *"//;s/"$//'
}

parse_config_array() {
    grep -o "\"$1\"\s*:\s*\[[^]]*\]" "$CONFIG_FILE" | sed 's/.*\[//;s/\]//' | tr -d '"' | tr ',' '\n' | sed 's/^ *//;s/ *$//' | grep -v '^$'
}

if [ ! -f "$CONFIG_FILE" ]; then
    echo "Error: Config file not found at $CONFIG_FILE"
    exit 1
fi

API_URL=$(parse_config_value "api_url" | sed 's:/*$::')
TOKEN=$(parse_config_value "agent_token")

if [ -z "$API_URL" ] || [ -z "$TOKEN" ]; then
    echo "Error: api_url or agent_token missing from config"
    exit 1
fi

# Parse log files to watch
LOG_FILES=()
while IFS= read -r line; do
    [ -n "$line" ] && LOG_FILES+=("$line")
done < <(parse_config_array "log_files" 2>/dev/null || true)

# Default log files if none configured
if [ ${#LOG_FILES[@]} -eq 0 ]; then
    for f in /var/log/syslog /var/log/auth.log /var/log/messages; do
        [ -f "$f" ] && LOG_FILES+=("$f")
    done
fi

# --- System Info Functions ---
get_os_info() {
    if [ -f /etc/os-release ]; then
        grep -m1 'PRETTY_NAME=' /etc/os-release | cut -d'"' -f2
    else
        uname -s -r
    fi
}

get_cpu_cores() {
    grep -c '^processor' /proc/cpuinfo 2>/dev/null || echo "1"
}

get_ram_total() {
    awk '/^MemTotal:/ { printf "%.2f", $2 / 1048576 }' /proc/meminfo
}

get_disk_total() {
    df -BG / 2>/dev/null | awk 'NR==2 { gsub("G",""); printf "%.2f", $2 }'
}

get_uptime() {
    local secs
    secs=$(awk '{ print int($1) }' /proc/uptime)
    local days=$((secs / 86400))
    local hours=$(( (secs % 86400) / 3600 ))
    local mins=$(( (secs % 3600) / 60 ))
    local result=""
    [ "$days" -gt 0 ] && result="${days}d "
    [ "$hours" -gt 0 ] || [ "$days" -gt 0 ] && result="${result}${hours}h "
    result="${result}${mins}m"
    echo "$result"
}

# --- Metric Collection ---
get_cpu_usage() {
    local cpu1 idle1 cpu2 idle2
    read -r cpu1 idle1 < <(awk '/^cpu / { total=0; for(i=2;i<=NF;i++) total+=$i; print total, $5+$6 }' /proc/stat)
    sleep 1
    read -r cpu2 idle2 < <(awk '/^cpu / { total=0; for(i=2;i<=NF;i++) total+=$i; print total, $5+$6 }' /proc/stat)

    local diff_total=$((cpu2 - cpu1))
    local diff_idle=$((idle2 - idle1))

    if [ "$diff_total" -eq 0 ]; then
        echo "0.00"
    else
        awk "BEGIN { printf \"%.2f\", 100.0 * ($diff_total - $diff_idle) / $diff_total }"
    fi
}

get_ram_usage() {
    awk '/^MemTotal:/ { total=$2 } /^MemAvailable:/ { avail=$2 } END { if(total>0) printf "%.2f", 100.0*(total-avail)/total; else print "0.00" }' /proc/meminfo
}

get_disk_usage() {
    df / 2>/dev/null | awk 'NR==2 { gsub("%",""); print $5 }'
}

# --- HTTP Helper ---
post_json() {
    local url="$1" data="$2"
    curl -s -X POST \
        -H "Content-Type: application/json" \
        -H "X-Agent-Token: $TOKEN" \
        -H "Accept: application/json" \
        -H "User-Agent: VPS-Agent/2.0" \
        -o /dev/null -w "%{http_code}" \
        --connect-timeout 5 \
        --max-time 10 \
        -d "$data" "$url" 2>/dev/null || echo "000"
}

# --- JSON string escaper ---
json_escape() {
    local s="$1"
    s="${s//\\/\\\\}"
    s="${s//\"/\\\"}"
    s="${s//$'\n'/\\n}"
    s="${s//$'\r'/\\r}"
    s="${s//$'\t'/\\t}"
    printf '%s' "$s"
}

# --- Log Streaming ---
: > "$LOG_BUFFER"  # Initialize empty buffer
LOG_BUFFER_LOCK="/tmp/vps-agent-log-buffer.lock"

flush_log_buffer() {
    # Atomically read and clear buffer
    (
        flock -n 200 || return
        if [ ! -s "$LOG_BUFFER" ]; then
            return
        fi

        local entries
        entries=$(cat "$LOG_BUFFER")
        : > "$LOG_BUFFER"

        # Build JSON array
        local json_array="[${entries%,}]"
        local payload="{\"logs\":${json_array}}"

        local status
        status=$(curl -s -X POST \
            -H "Content-Type: application/json" \
            -H "X-Agent-Token: $TOKEN" \
            -H "Accept: application/json" \
            --connect-timeout 5 \
            --max-time 10 \
            -o /dev/null -w "%{http_code}" \
            -d "$payload" "${API_URL}/api/v1/agent/logs" 2>/dev/null || echo "000")

        if [ "$status" != "200" ] && [ "$status" != "201" ]; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] Failed to send logs (HTTP $status)" >&2
        fi
    ) 200>"$LOG_BUFFER_LOCK"
}

stream_log_file() {
    local file="$1"
    local line_count=0

    tail -n 0 -F "$file" 2>/dev/null | while IFS= read -r line; do
        # Detect log level
        local level="info"
        case "$line" in
            *[Ee]rror*|*ERROR*|*[Ff]ailed*|*FAILED*)   level="error" ;;
            *[Ww]arning*|*WARN*|*[Ww]arn*)             level="warning" ;;
            *[Cc]ritical*|*CRIT*|*[Ee]mergency*|*EMERG*) level="critical" ;;
            *[Nn]otice*|*NOTICE*)                       level="notice" ;;
            *[Dd]ebug*|*DEBUG*)                         level="debug" ;;
        esac

        local escaped_msg
        escaped_msg=$(json_escape "$line")
        local ts
        ts=$(date -u '+%Y-%m-%dT%H:%M:%SZ')

        (
            flock 200
            printf '{"source_file":"%s","level":"%s","message":"%s","logged_at":"%s"},' \
                "$file" "$level" "$escaped_msg" "$ts" >> "$LOG_BUFFER"
        ) 200>"$LOG_BUFFER_LOCK"

        line_count=$((line_count + 1))
        if [ "$line_count" -ge 20 ]; then
            flush_log_buffer
            line_count=0
        fi
    done
}

# --- Periodic log buffer flusher ---
start_log_flusher() {
    while true; do
        sleep 2
        flush_log_buffer
    done
}

# --- Main ---
echo "Starting VPS Monitoring Agent (Bash)..."
echo "API: $API_URL"
echo "Log files: ${LOG_FILES[*]:-none}"

# 1. Handshake
os_info=$(get_os_info)
cpu_cores=$(get_cpu_cores)
ram_total=$(get_ram_total)
disk_total=$(get_disk_total)

handshake_data=$(printf '{"os_info":"%s","cpu_cores":%s,"ram_total":%s,"disk_total":%s}' \
    "$(json_escape "$os_info")" "$cpu_cores" "$ram_total" "$disk_total")

echo "Performing handshake..."
status=$(post_json "${API_URL}/api/v1/agent/handshake" "$handshake_data")

if [ "$status" != "200" ]; then
    echo "Handshake failed (HTTP $status). Check config and dashboard availability."
    exit 1
fi
echo "Handshake successful."

# 2. Start log streaming in background
for logfile in "${LOG_FILES[@]}"; do
    if [ -f "$logfile" ] && [ -r "$logfile" ]; then
        echo "Streaming: $logfile"
        stream_log_file "$logfile" &
    else
        echo "Skipping (not readable): $logfile"
    fi
done

# Start periodic log flusher
start_log_flusher &

# 3. Main metrics loop
echo "Starting metric reporting loop..."
while true; do
    cpu=$(get_cpu_usage)   # takes ~1 second internally
    ram=$(get_ram_usage)
    disk=$(get_disk_usage)
    uptime_str=$(get_uptime)

    metrics_data=$(printf '{"cpu_usage":%s,"ram_usage":%s,"disk_usage":%s,"uptime":"%s"}' \
        "$cpu" "$ram" "$disk" "$uptime_str")

    status=$(post_json "${API_URL}/api/v1/agent/metrics" "$metrics_data")

    if [ "$status" = "200" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Metrics: CPU ${cpu}%, RAM ${ram}%, Disk ${disk}%"
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Failed to report metrics (HTTP $status)"
    fi

    sleep 4  # 4s + 1s from CPU = ~5s cycle
done
