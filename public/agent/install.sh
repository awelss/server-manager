#!/usr/bin/env bash

# Exit on any error
set -e

# Stylized colors
RESET="\033[0m"
BOLD="\033[1m"
GREEN="\033[38;2;16;185;129m"
CYAN="\033[38;2;6;182;212m"
YELLOW="\033[38;2;245;158;11m"
RED="\033[38;2;239;68;68m"

# Print banner
echo -e "${BOLD}${CYAN}"
echo "  __   _____  ___    __                     _   "
echo "  \ \ / / _ \/ __|  / _\ ___ _ ____   _____| |_ "
echo "   \ V /|  __/\__ \  \ \ / _ \ '__\ \ / / _ \ __|"
echo "    \_/ |_|   |___/  _\ \  __/ |   \ V /  __/ |_ "
echo "                    \__/\___|_|    \_/ \___|\__|"
echo "           VPS Resource Monitoring Agent v2.0"
echo -e "${RESET}"

# Parse and validate arguments
API_URL=$1
TOKEN=$2

if [ -z "$API_URL" ] || [ -z "$TOKEN" ]; then
    echo -e "${RED}${BOLD}Error: Missing arguments.${RESET}"
    echo -e "Usage: curl -sSL http://your-dashboard/agent/install.sh | sudo bash -s -- <API_URL> <TOKEN>"
    exit 1
fi

# Ensure running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}${BOLD}Error: Please run this installer as root (sudo).${RESET}"
    exit 1
fi

# Check for curl
if ! command -v curl &>/dev/null; then
    echo -e "${RED}${BOLD}Error: curl is required. Please install curl and try again.${RESET}"
    exit 1
fi

echo -e "${CYAN}* Preparing directories...${RESET}"
mkdir -p /etc/vps-agent
mkdir -p /opt/vps-agent

echo -e "${CYAN}* Creating configuration...${RESET}"
cat <<EOF > /etc/vps-agent/config.json
{
  "api_url": "${API_URL}",
  "agent_token": "${TOKEN}",
  "log_files": ["/var/log/syslog", "/var/log/auth.log"]
}
EOF
chmod 600 /etc/vps-agent/config.json

echo -e "${CYAN}* Downloading latest agent script from dashboard...${RESET}"
AGENT_SOURCE="${API_URL}/agent/agent.sh"
echo -e "Downloading: ${AGENT_SOURCE}"

if ! curl -sSL "$AGENT_SOURCE" -o /opt/vps-agent/agent.sh; then
    echo -e "${RED}${BOLD}Error: Failed to download agent script from ${AGENT_SOURCE}${RESET}"
    echo -e "Please check if your dashboard IP/Domain is correct and accessible."
    exit 1
fi

chmod +x /opt/vps-agent/agent.sh
echo -e "${GREEN}✔ Agent script saved successfully.${RESET}"

# Stop old agent if running
if systemctl is-active --quiet vps-agent.service 2>/dev/null; then
    echo -e "${CYAN}* Stopping existing agent...${RESET}"
    systemctl stop vps-agent.service
fi

# Setup background service (Systemd support check)
if command -v systemctl &>/dev/null; then
    echo -e "${CYAN}* Registering systemd service...${RESET}"

    cat <<EOF > /etc/systemd/system/vps-agent.service
[Unit]
Description=VPS Resource Monitoring Agent
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/vps-agent
ExecStart=/usr/bin/bash /opt/vps-agent/agent.sh
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

    echo -e "${CYAN}* Starting vps-agent service...${RESET}"
    systemctl daemon-reload
    systemctl enable vps-agent.service
    systemctl restart vps-agent.service

    echo -e "\n${BOLD}${GREEN}✔ Installation successful! vps-agent.service is running in the background.${RESET}"
    echo -e "You can check its status using: ${BOLD}systemctl status vps-agent.service${RESET}"
else
    # Fallback for systems without systemd
    echo -e "${YELLOW}! Warning: systemd was not detected on this machine.${RESET}"
    echo -e "${CYAN}* Starting agent in background (fallback mode)...${RESET}"

    nohup /usr/bin/bash /opt/vps-agent/agent.sh >/var/log/vps-agent.log 2>&1 &

    echo -e "\n${BOLD}${GREEN}✔ Installation successful! Agent is running in the background (PID: $!).${RESET}"
    echo -e "Log file is located at: /var/log/vps-agent.log"
fi

echo -e "\n${BOLD}${CYAN}Thank you for using VPS Control Hub! Watch your dashboard for incoming metrics.${RESET}"
