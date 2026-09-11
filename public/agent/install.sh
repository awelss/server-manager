#!/usr/bin/env bash
set -e

RESET="\033[0m"
BOLD="\033[1m"
GREEN="\033[38;2;16;185;129m"
CYAN="\033[38;2;6;182;212m"
YELLOW="\033[38;2;245;158;11m"
RED="\033[38;2;239;68;68m"

echo -e "${BOLD}${CYAN}"
echo "  __   _____  ___    __                     _   "
echo "  \ \ / / _ \/ __|  / _\ ___ _ ____   _____| |_ "
echo "   \ V /|  __/\__ \  \ \ / _ \ '__\ \ / / _ \ __|"
echo "    \_/ |_|   |___/  _\ \  __/ |   \ V /  __/ |_ "
echo "                    \__/\___|_|    \_/ \___|\__|"
echo "           VPS Resource Monitoring Agent v3.0"
echo -e "${RESET}"

API_URL=$1
TOKEN=$2

if [ -z "$API_URL" ] || [ -z "$TOKEN" ]; then
    echo -e "${RED}${BOLD}Error: Missing arguments.${RESET}"
    echo -e "Usage: curl -sSL https://your-dashboard/agent/install.sh | sudo bash -s -- <API_URL> <TOKEN>"
    exit 1
fi

if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}${BOLD}Error: Please run this installer as root (sudo).${RESET}"
    exit 1
fi

if ! command -v curl &>/dev/null; then
    echo -e "${RED}${BOLD}Error: curl is required.${RESET}"
    exit 1
fi

mkdir -p /etc/vps-agent /opt/vps-agent

# Metrics-only by default. Add log files manually only when log streaming is required.
cat <<EOF > /etc/vps-agent/config.json
{
  "api_url": "${API_URL}",
  "agent_token": "${TOKEN}",
  "log_files": [],
  "health_urls": [],
  "backup_paths": ["/var/backups", "/opt/backups", "/backup", "/root/backups"]
}
EOF
chmod 600 /etc/vps-agent/config.json

echo -e "${CYAN}* Downloading latest agent script from dashboard...${RESET}"
AGENT_SOURCE="${API_URL}/agent/agent.sh"

if ! curl -fsSL "$AGENT_SOURCE" -o /opt/vps-agent/agent.sh; then
    echo -e "${RED}${BOLD}Error: Failed to download agent script from ${AGENT_SOURCE}${RESET}"
    exit 1
fi

chmod 755 /opt/vps-agent/agent.sh

if systemctl is-active --quiet vps-agent.service 2>/dev/null; then
    systemctl stop vps-agent.service
fi

if command -v systemctl &>/dev/null; then
    cat <<EOF > /etc/systemd/system/vps-agent.service
[Unit]
Description=VPS Resource Monitoring Agent
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/vps-agent
ExecStart=/usr/bin/bash /opt/vps-agent/agent.sh
Restart=always
RestartSec=5
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=read-only
ProtectSystem=full
ReadWritePaths=/tmp /etc/vps-agent

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable vps-agent.service
    systemctl restart vps-agent.service

    echo -e "\n${BOLD}${GREEN}✔ Installation successful. vps-agent.service is running.${RESET}"
    echo -e "Check: ${BOLD}systemctl status vps-agent.service --no-pager${RESET}"
else
    echo -e "${YELLOW}! systemd not detected; starting fallback background process.${RESET}"
    nohup /usr/bin/bash /opt/vps-agent/agent.sh >/var/log/vps-agent.log 2>&1 &
fi

echo -e "${CYAN}Metrics, service health, Docker status and backup freshness are enabled. Log streaming is disabled by default.${RESET}"
