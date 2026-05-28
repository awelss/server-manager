#!/usr/bin/env python3
import os
import sys
import time
import json
import shutil
import urllib.request
import urllib.error

CONFIG_PATH = "/etc/vps-agent/config.json"

def read_config():
    """Read agent configuration (API URL and Server Token)."""
    if not os.path.exists(CONFIG_PATH):
        print(f"Error: Configuration file not found at {CONFIG_PATH}")
        sys.exit(1)
    
    try:
        with open(CONFIG_PATH, 'r') as f:
            return json.load(f)
    except Exception as e:
        print(f"Error parsing configuration: {e}")
        sys.exit(1)

def get_os_info():
    """Retrieve OS distribution name."""
    try:
        if os.path.exists("/etc/os-release"):
            with open("/etc/os-release", "r") as f:
                for line in f:
                    if line.startswith("PRETTY_NAME="):
                        return line.split("=")[1].strip().strip('"')
        return "Linux (Unknown OS)"
    except Exception:
        return "Linux"

def get_cpu_cores():
    """Get the number of CPU cores."""
    try:
        return os.cpu_count() or 1
    except Exception:
        return 1

def get_ram_total_gb():
    """Get total RAM in GB."""
    try:
        with open("/proc/meminfo", "r") as f:
            for line in f:
                if line.startswith("MemTotal:"):
                    mem_kb = int(line.split()[1])
                    return round(mem_kb / (1024 * 1024), 2)
        return 1.0
    except Exception:
        return 1.0

def get_disk_total_gb():
    """Get total Disk size in GB for root partition."""
    try:
        total, used, free = shutil.disk_usage("/")
        return round(total / (1024 * 1024 * 1024), 2)
    except Exception:
        return 10.0

def get_uptime_str():
    """Format system uptime into a human-readable string."""
    try:
        with open("/proc/uptime", "r") as f:
            uptime_seconds = float(f.readline().split()[0])
        
        days = uptime_seconds // 86400
        hours = (uptime_seconds % 86400) // 3600
        minutes = (uptime_seconds % 3600) // 60
        
        parts = []
        if days > 0:
            parts.append(f"{int(days)}d")
        if hours > 0 or days > 0:
            parts.append(f"{int(hours)}h")
        parts.append(f"{int(minutes)}m")
        
        return " ".join(parts)
    except Exception:
        return "Unknown"

def get_cpu_reading():
    """Read CPU time stats from /proc/stat."""
    with open("/proc/stat", "r") as f:
        line = f.readline()
    fields = [float(x) for x in line.split()[1:9]]
    # User, Nice, System, Idle, Iowait, Irq, Softirq, Steal
    total_time = sum(fields)
    idle_time = fields[3] + fields[4]  # idle + iowait
    return total_time, idle_time

def get_cpu_usage_pct():
    """Calculate CPU usage percentage by comparing readings over 1 second."""
    try:
        t1, i1 = get_cpu_reading()
        time.sleep(1)
        t2, i2 = get_cpu_reading()
        
        diff_total = t2 - t1
        diff_idle = i2 - i1
        
        if diff_total == 0:
            return 0.0
            
        usage = 100.0 * (diff_total - diff_idle) / diff_total
        return round(max(0.0, min(100.0, usage)), 2)
    except Exception as e:
        print(f"Error reading CPU usage: {e}")
        return 0.0

def get_ram_usage_pct():
    """Calculate memory usage percentage."""
    try:
        mem_total = 0
        mem_available = 0
        with open("/proc/meminfo", "r") as f:
            for line in f:
                if line.startswith("MemTotal:"):
                    mem_total = int(line.split()[1])
                elif line.startswith("MemAvailable:"):
                    mem_available = int(line.split()[1])
        
        if mem_total == 0:
            return 0.0
            
        used = mem_total - mem_available
        usage = (used / mem_total) * 100.0
        return round(max(0.0, min(100.0, usage)), 2)
    except Exception as e:
        print(f"Error reading RAM usage: {e}")
        return 0.0

def get_disk_usage_pct():
    """Calculate disk usage percentage for root partition."""
    try:
        total, used, free = shutil.disk_usage("/")
        usage = (used / total) * 100.0
        return round(max(0.0, min(100.0, usage)), 2)
    except Exception as e:
        print(f"Error reading Disk usage: {e}")
        return 0.0

def send_post_request(url, data, token):
    """Securely send JSON POST request to the API."""
    headers = {
        "Content-Type": "application/json",
        "X-Agent-Token": token,
        "Accept": "application/json",
        "User-Agent": "VPS-Agent/1.0"
    }
    
    req = urllib.request.Request(
        url,
        data=json.dumps(data).encode("utf-8"),
        headers=headers,
        method="POST"
    )
    
    try:
        with urllib.request.urlopen(req, timeout=5) as response:
            res_data = json.loads(response.read().decode("utf-8"))
            return True, res_data
    except urllib.error.HTTPError as e:
        try:
            err_msg = json.loads(e.read().decode("utf-8")).get("message", e.reason)
        except Exception:
            err_msg = e.reason
        return False, f"HTTP Error {e.code}: {err_msg}"
    except Exception as e:
        return False, str(e)

def main():
    print("Starting VPS Resource Monitoring Agent...")
    config = read_config()
    api_url = config.get("api_url").rstrip("/")
    token = config.get("agent_token")
    
    # 1. Perform Handshake
    handshake_url = f"{api_url}/api/v1/agent/handshake"
    handshake_data = {
        "os_info": get_os_info(),
        "cpu_cores": get_cpu_cores(),
        "ram_total": get_ram_total_gb(),
        "disk_total": get_disk_total_gb()
    }
    
    print(f"Performing handshake with {handshake_url}...")
    success, result = send_post_request(handshake_url, handshake_data, token)
    
    if not success:
        print(f"Handshake failed: {result}")
        print("Please check your configuration or make sure the server dashboard is online.")
        sys.exit(1)
        
    print(f"Handshake completed: {result.get('message')}")
    
    # 2. Main Monitoring Loop
    metrics_url = f"{api_url}/api/v1/agent/metrics"
    print(f"Starting metric reports to {metrics_url}...")
    
    while True:
        # Note: get_cpu_usage_pct takes 1 second internally for delta comparison
        cpu = get_cpu_usage_pct()
        ram = get_ram_usage_pct()
        disk = get_disk_usage_pct()
        uptime = get_uptime_str()
        
        metrics_data = {
            "cpu_usage": cpu,
            "ram_usage": ram,
            "disk_usage": disk,
            "uptime": uptime
        }
        
        success, response = send_post_request(metrics_url, metrics_data, token)
        if success:
            print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Metrics reported: CPU {cpu}%, RAM {ram}%, Disk {disk}%")
        else:
            print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Failed to report metrics: {response}")
            
        # Sleep for the remaining interval (4 seconds, since CPU delta calculation took 1 second)
        time.sleep(4)

if __name__ == "__main__":
    main()
