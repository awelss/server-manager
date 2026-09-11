#!/usr/bin/env bash
set -euo pipefail

CONFIG_FILE="/etc/vps-agent/config.json"
LOG_BUFFER="/tmp/vps-agent-log-buffer.json"
LOG_BUFFER_LOCK="/tmp/vps-agent-log-buffer.lock"

parse_config_value() {
    grep -o "\"$1\"\s*:\s*\"[^\"]*\"" "$CONFIG_FILE" | sed 's/.*: *"//;s/"$//'
}

parse_config_array() {
    grep -o "\"$1\"\s*:\s*\[[^]]*\]" "$CONFIG_FILE" | sed 's/.*\[//;s/\]//' | tr -d '"' | tr ',' '\n' | sed 's/^ *//;s/ *$//' | grep -v '^$'
}

json_escape() {
    local s="$1"
    s="${s//\\/\\\\}"
    s="${s//\"/\\\"}"
    s="${s//$'\n'/\\n}"
    s="${s//$'\r'/\\r}"
    s="${s//$'\t'/\\t}"
    printf '%s' "$s"
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

LOG_FILES=()
if grep -q '"log_files"' "$CONFIG_FILE"; then
    while IFS= read -r line; do
        [ -n "$line" ] && LOG_FILES+=("$line")
    done < <(parse_config_array "log_files" 2>/dev/null || true)
else
    for f in /var/log/syslog /var/log/auth.log /var/log/messages; do
        [ -f "$f" ] && LOG_FILES+=("$f")
    done
fi

HEALTH_URLS=()
while IFS= read -r line; do
    [ -n "$line" ] && HEALTH_URLS+=("$line")
done < <(parse_config_array "health_urls" 2>/dev/null || true)

BACKUP_PATHS=()
while IFS= read -r line; do
    [ -n "$line" ] && BACKUP_PATHS+=("$line")
done < <(parse_config_array "backup_paths" 2>/dev/null || true)

if [ ${#BACKUP_PATHS[@]} -eq 0 ]; then
    BACKUP_PATHS=("/var/backups" "/opt/backups" "/backup" "/root/backups")
fi

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
    local secs days hours mins result
    secs=$(awk '{ print int($1) }' /proc/uptime)
    days=$((secs / 86400))
    hours=$(((secs % 86400) / 3600))
    mins=$(((secs % 3600) / 60))
    result=""
    [ "$days" -gt 0 ] && result="${days}d "
    if [ "$hours" -gt 0 ] || [ "$days" -gt 0 ]; then
        result="${result}${hours}h "
    fi
    echo "${result}${mins}m"
}

get_cpu_breakdown() {
    local t1 idle1 wait1 steal1 t2 idle2 wait2 steal2 diff_total diff_idle diff_wait diff_steal
    read -r t1 idle1 wait1 steal1 < <(awk '/^cpu / { total=0; for(i=2;i<=9;i++) total+=$i; print total, $5+$6, $6, $9 }' /proc/stat)
    sleep 1
    read -r t2 idle2 wait2 steal2 < <(awk '/^cpu / { total=0; for(i=2;i<=9;i++) total+=$i; print total, $5+$6, $6, $9 }' /proc/stat)

    diff_total=$((t2 - t1))
    diff_idle=$((idle2 - idle1))
    diff_wait=$((wait2 - wait1))
    diff_steal=$((steal2 - steal1))

    if [ "$diff_total" -le 0 ]; then
        echo "0.00 0.00 0.00"
        return
    fi

    awk "BEGIN { printf \"%.2f %.2f %.2f\\n\", 100.0*($diff_total-$diff_idle)/$diff_total, 100.0*$diff_wait/$diff_total, 100.0*$diff_steal/$diff_total }"
}

get_ram_usage() {
    awk '/^MemTotal:/ { total=$2 } /^MemAvailable:/ { avail=$2 } END { if(total>0) printf "%.2f", 100.0*(total-avail)/total; else print "0.00" }' /proc/meminfo
}

get_swap_usage() {
    awk '/^SwapTotal:/ { total=$2 } /^SwapFree:/ { free=$2 } END { if(total>0) printf "%.2f", 100.0*(total-free)/total; else print "0.00" }' /proc/meminfo
}

get_disk_usage() {
    df -P / 2>/dev/null | awk 'NR==2 { gsub("%",""); print $5 }'
}

get_disk_free_gb() {
    df -PB1 / 2>/dev/null | awk 'NR==2 { printf "%.2f", $4 / 1073741824 }'
}

get_inode_usage() {
    df -Pi / 2>/dev/null | awk 'NR==2 { gsub("%",""); print $5 }'
}

get_load_values() {
    awk '{ print $1, $2, $3 }' /proc/loadavg
}

get_process_stats() {
    ps -eo stat= 2>/dev/null | awk '{ total++; if ($1 ~ /^Z/) zombies++ } END { printf "%d %d\n", zombies+0, total+0 }'
}

get_network_bytes() {
    awk -F'[: ]+' 'NR>2 && $2 != "lo" { rx += $3; tx += $11 } END { printf "%.0f %.0f\n", rx+0, tx+0 }' /proc/net/dev
}

post_json() {
    local url="$1" data="$2"
    curl -s -X POST \
        -H "Content-Type: application/json" \
        -H "X-Agent-Token: $TOKEN" \
        -H "Accept: application/json" \
        -H "User-Agent: VPS-Agent/3.0" \
        -o /dev/null -w "%{http_code}" \
        --connect-timeout 5 \
        --max-time 15 \
        -d "$data" "$url" 2>/dev/null || echo "000"
}

collect_services_json() {
    local candidates=(nginx postgresql mariadb mysql redis-server redis docker cloudflared k3s)
    local entries="" unit state

    if ! command -v systemctl >/dev/null 2>&1; then
        echo '[]'
        return
    fi

    for unit in "${candidates[@]}"; do
        if systemctl list-unit-files "${unit}.service" --no-legend 2>/dev/null | grep -q "${unit}.service"; then
            state=$(systemctl is-active "${unit}.service" 2>/dev/null || true)
            case "$state" in
                active|inactive|failed|activating|deactivating|reloading) ;;
                *) state="unknown" ;;
            esac
            entries="${entries}{\"name\":\"$(json_escape "$unit")\",\"state\":\"$state\"},"
        fi
    done

    if command -v pm2 >/dev/null 2>&1; then
        if pgrep -f 'PM2.*God Daemon' >/dev/null 2>&1; then
            state="active"
        else
            state="inactive"
        fi
        entries="${entries}{\"name\":\"pm2\",\"state\":\"$state\"},"
    fi

    printf '[%s]' "${entries%,}"
}

collect_docker_json() {
    local entries="" stats name state status cpu mempct memusage restarts line

    if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
        echo '[]'
        return
    fi

    stats=$(docker stats --no-stream --format '{{.Name}}|{{.CPUPerc}}|{{.MemPerc}}|{{.MemUsage}}' 2>/dev/null || true)

    while IFS='|' read -r name state status; do
        [ -z "$name" ] && continue
        cpu=$(printf '%s\n' "$stats" | awk -F'|' -v n="$name" '$1==n { gsub("%","",$2); print $2; exit }')
        mempct=$(printf '%s\n' "$stats" | awk -F'|' -v n="$name" '$1==n { gsub("%","",$3); print $3; exit }')
        memusage=$(printf '%s\n' "$stats" | awk -F'|' -v n="$name" '$1==n { print $4; exit }')
        restarts=$(docker inspect -f '{{.RestartCount}}' "$name" 2>/dev/null || echo 0)
        cpu=${cpu:-0}
        mempct=${mempct:-0}

        entries="${entries}{\"name\":\"$(json_escape "$name")\",\"state\":\"$(json_escape "$state")\",\"status\":\"$(json_escape "$status")\",\"cpu_percent\":${cpu},\"memory_percent\":${mempct},\"memory_usage\":\"$(json_escape "${memusage:-}")\",\"restart_count\":${restarts}},"
    done < <(docker ps -a --format '{{.Names}}|{{.State}}|{{.Status}}' 2>/dev/null | head -50)

    printf '[%s]' "${entries%,}"
}

collect_backup_json() {
    local existing=() path latest mtime size file now age status

    for path in "${BACKUP_PATHS[@]}"; do
        [ -d "$path" ] && existing+=("$path")
    done

    if [ ${#existing[@]} -eq 0 ]; then
        echo '{"status":"unknown","latest_file":null,"age_minutes":null,"size_bytes":null}'
        return
    fi

    latest=$(find "${existing[@]}" -maxdepth 2 -type f \
        \( -name '*.sql' -o -name '*.sql.gz' -o -name '*.dump' -o -name '*.gz' -o -name '*.tgz' -o -name '*.tar' -o -name '*.tar.gz' -o -name '*.zip' \) \
        -printf '%T@|%s|%p\n' 2>/dev/null | sort -nr | head -1 || true)

    if [ -z "$latest" ]; then
        echo '{"status":"missing","latest_file":null,"age_minutes":null,"size_bytes":null}'
        return
    fi

    mtime=${latest%%|*}
    latest=${latest#*|}
    size=${latest%%|*}
    file=${latest#*|}
    mtime=${mtime%%.*}
    now=$(date +%s)
    age=$(((now - mtime) / 60))
    [ "$age" -lt 0 ] && age=0

    if [ "$age" -le 1440 ]; then
        status="ok"
    else
        status="stale"
    fi

    printf '{"status":"%s","latest_file":"%s","age_minutes":%d,"size_bytes":%s}' \
        "$status" "$(json_escape "$file")" "$age" "$size"
}

collect_http_checks_json() {
    local entries="" url result code seconds latency ok

    for url in "${HEALTH_URLS[@]}"; do
        result=$(curl -k -sS -o /dev/null -w '%{http_code}|%{time_total}' --connect-timeout 3 --max-time 8 "$url" 2>/dev/null || echo '000|0')
        code=${result%%|*}
        seconds=${result#*|}
        latency=$(awk "BEGIN { printf \"%.0f\", (${seconds:-0}) * 1000 }")
        if [ "$code" -ge 200 ] 2>/dev/null && [ "$code" -lt 500 ] 2>/dev/null; then
            ok=true
        else
            ok=false
        fi
        entries="${entries}{\"url\":\"$(json_escape "$url")\",\"status_code\":${code:-0},\"ok\":${ok},\"latency_ms\":${latency}},"
    done

    printf '[%s]' "${entries%,}"
}

report_infrastructure_status() {
    local services docker backup checks payload status
    services=$(collect_services_json)
    docker=$(collect_docker_json)
    backup=$(collect_backup_json)
    checks=$(collect_http_checks_json)
    payload="{\"services\":${services},\"docker\":${docker},\"backup\":${backup},\"http_checks\":${checks}}"
    status=$(post_json "${API_URL}/api/v1/agent/status" "$payload")

    if [ "$status" != "200" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Failed infrastructure status (HTTP $status)" >&2
    fi
}

: > "$LOG_BUFFER"

flush_log_buffer() {
    (
        flock -n 200 || return
        [ ! -s "$LOG_BUFFER" ] && return

        local entries json_array payload status
        entries=$(cat "$LOG_BUFFER")
        : > "$LOG_BUFFER"
        json_array="[${entries%,}]"
        payload="{\"logs\":${json_array}}"
        status=$(post_json "${API_URL}/api/v1/agent/logs" "$payload")

        if [ "$status" != "200" ] && [ "$status" != "201" ]; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] Failed to send logs (HTTP $status)" >&2
        fi
    ) 200>"$LOG_BUFFER_LOCK"
}

stream_log_file() {
    local file="$1" line_count=0

    tail -n 0 -F "$file" 2>/dev/null | while IFS= read -r line; do
        local level="info" escaped_msg ts
        case "$line" in
            *[Ee]rror*|*ERROR*|*[Ff]ailed*|*FAILED*) level="error" ;;
            *[Ww]arning*|*WARN*|*[Ww]arn*) level="warning" ;;
            *[Cc]ritical*|*CRIT*|*[Ee]mergency*|*EMERG*) level="critical" ;;
            *[Nn]otice*|*NOTICE*) level="notice" ;;
            *[Dd]ebug*|*DEBUG*) level="debug" ;;
        esac

        escaped_msg=$(json_escape "$line")
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

start_log_flusher() {
    while true; do
        sleep 2
        flush_log_buffer
    done
}

echo "Starting VPS Monitoring Agent v3.0..."
echo "API: $API_URL"
echo "Log streaming: ${LOG_FILES[*]:-disabled}"

os_info=$(get_os_info)
cpu_cores=$(get_cpu_cores)
ram_total=$(get_ram_total)
disk_total=$(get_disk_total)
handshake_data=$(printf '{"os_info":"%s","cpu_cores":%s,"ram_total":%s,"disk_total":%s}' \
    "$(json_escape "$os_info")" "$cpu_cores" "$ram_total" "$disk_total")

status=$(post_json "${API_URL}/api/v1/agent/handshake" "$handshake_data")
if [ "$status" != "200" ]; then
    echo "Handshake failed (HTTP $status). Check config and dashboard availability."
    exit 1
fi
echo "Handshake successful."

for logfile in "${LOG_FILES[@]}"; do
    if [ -f "$logfile" ] && [ -r "$logfile" ]; then
        stream_log_file "$logfile" &
    fi
done

if [ ${#LOG_FILES[@]} -gt 0 ]; then
    start_log_flusher &
fi

report_infrastructure_status &

status_counter=0
while true; do
    read -r cpu iowait steal < <(get_cpu_breakdown)
    read -r load1 load5 load15 < <(get_load_values)
    read -r zombies process_count < <(get_process_stats)
    read -r network_rx network_tx < <(get_network_bytes)

    ram=$(get_ram_usage)
    swap=$(get_swap_usage)
    disk=$(get_disk_usage)
    disk_free=$(get_disk_free_gb)
    inode=$(get_inode_usage)
    uptime_str=$(get_uptime)

    metrics_data=$(printf '{"cpu_usage":%s,"cpu_iowait":%s,"cpu_steal":%s,"load_1":%s,"load_5":%s,"load_15":%s,"ram_usage":%s,"swap_usage":%s,"zombie_processes":%s,"process_count":%s,"disk_usage":%s,"disk_free_gb":%s,"inode_usage":%s,"network_rx_bytes":%s,"network_tx_bytes":%s,"uptime":"%s"}' \
        "$cpu" "$iowait" "$steal" "$load1" "$load5" "$load15" "$ram" "$swap" "$zombies" "$process_count" "$disk" "$disk_free" "$inode" "$network_rx" "$network_tx" "$(json_escape "$uptime_str")")

    status=$(post_json "${API_URL}/api/v1/agent/metrics" "$metrics_data")
    if [ "$status" = "200" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] CPU ${cpu}% steal ${steal}% iowait ${iowait}% | RAM ${ram}% | Disk ${disk}% | zombies ${zombies}"
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Failed metrics (HTTP $status)"
    fi

    status_counter=$((status_counter + 1))
    if [ "$status_counter" -ge 12 ]; then
        report_infrastructure_status &
        status_counter=0
    fi

    sleep 4

done
