<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white" alt="Vue.js">
  <img src="https://img.shields.io/badge/WebSocket-Reverb-818CF8?style=for-the-badge&logo=socketdotio&logoColor=white" alt="WebSocket">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="License">
</p>

<h1 align="center">VPS Control Hub</h1>

<p align="center">
  Open-source, real-time, agent-based server monitoring dashboard.<br>
  Install 1 command di VPS — langsung monitor dari browser.
</p>

<p align="center">
  <a href="https://monitor.ireng.uk">Live Demo</a> &middot;
  <a href="#installation">Installation</a> &middot;
  <a href="#agent-setup">Agent Setup</a> &middot;
  <a href="#features">Features</a>
</p>

---

## Features

- **Real-time Monitoring** — CPU, RAM, Disk, Uptime via WebSocket (bukan polling)
- **Load Average** — 1min / 5min / 15min dengan color-coded indicator
- **Process List** — Top 15 proses sorted by CPU usage, update real-time
- **Live Log Streaming** — Stream syslog, auth.log langsung ke dashboard
- **Web SSH Terminal** — Akses terminal VPS dari browser via xterm.js
- **WhatsApp Alert** — Notifikasi threshold-based (CPU > 80%? langsung dapet WA)
- **Multi-user + RBAC** — Role admin/user, assign server ke user tertentu
- **Agent Auto-detect** — Python3 available? pakai Python agent. Kalau tidak, fallback ke Bash
- **Self-hosted** — Gratis, no vendor lock-in, data kamu sendiri

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.4 |
| Frontend | Vue 3, Inertia.js, Tailwind CSS |
| Real-time | Laravel Reverb (self-hosted WebSocket) |
| Database | MySQL / MariaDB |
| Terminal | xterm.js + Node.js SSH relay (ssh2) |
| Agent | Python 3 (primary) / Bash (fallback) |
| Alert | WhatsApp API integration |

## Requirements

- PHP >= 8.4 with extensions: pdo_mysql, mbstring, bcmath, gd
- Composer
- Node.js >= 18
- MySQL / MariaDB
- Nginx (recommended) or Apache
- Supervisor atau systemd (untuk background services)

---

## Installation

### 1. Clone Repository

```bash
git clone https://github.com/idlanyor/server-manager.git
cd server-manager
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` sesuai environment kamu:

```env
# App
APP_NAME="VPS Control Hub"
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=server_manager
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Session
SESSION_DOMAIN=your-domain.com

# Broadcasting (Reverb WebSocket)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=your-domain.com
REVERB_PORT=443
REVERB_SCHEME=https

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# WhatsApp Alert (optional)
WHATSAPP_API_URL=https://your-wa-api.com
WHATSAPP_API_KEY=your-api-key
```

### 4. Database Setup

```bash
mysql -u root -e "CREATE DATABASE server_manager;"
php artisan migrate
```

### 5. Create Admin User

```bash
php artisan tinker
```

```php
App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('your-password'),
    'role' => 'admin',
    'email_verified_at' => now(),
]);
```

### 6. Build Frontend

```bash
npm run build
```

### 7. Set Permissions

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/server-manager/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Reverb WebSocket
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 3600;
    }

    # Terminal WebSocket (optional - jika pakai web terminal)
    location /ws/terminal {
        proxy_pass http://127.0.0.1:8615;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header Cookie $http_cookie;
        proxy_read_timeout 3600;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/your-config.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## Background Services

Aplikasi ini butuh beberapa background service. Buat systemd unit files berikut:

### Queue Worker

```bash
sudo tee /etc/systemd/system/server-manager-queue.service << 'EOF'
[Unit]
Description=Server Manager Queue Worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/server-manager
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
```

### Reverb WebSocket Server

```bash
sudo tee /etc/systemd/system/server-manager-reverb.service << 'EOF'
[Unit]
Description=Server Manager Reverb WebSocket
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/server-manager
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
```

### Terminal SSH Relay (optional)

```bash
sudo tee /etc/systemd/system/server-manager-terminal.service << 'EOF'
[Unit]
Description=Server Manager Terminal WebSocket Relay
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/server-manager
ExecStart=/usr/bin/node terminal-server.cjs
Environment=TERMINAL_PORT=8615
Environment=LARAVEL_URL=https://your-domain.com
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
```

### Scheduler Timer

```bash
sudo tee /etc/systemd/system/server-manager-scheduler.timer << 'EOF'
[Unit]
Description=Server Manager Scheduler Timer

[Timer]
OnCalendar=*:*:00
Persistent=true

[Install]
WantedBy=timers.target
EOF

sudo tee /etc/systemd/system/server-manager-scheduler.service << 'EOF'
[Unit]
Description=Server Manager Scheduler

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/var/www/server-manager
ExecStart=/usr/bin/php artisan schedule:run --no-interaction
EOF
```

### Enable & Start Semua Services

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now server-manager-queue
sudo systemctl enable --now server-manager-reverb
sudo systemctl enable --now server-manager-terminal    # optional
sudo systemctl enable --now server-manager-scheduler.timer
```

Verifikasi:

```bash
sudo systemctl status server-manager-queue server-manager-reverb
```

---

## Agent Setup

### Install Agent di VPS Target

1. Login ke dashboard, klik **"Register VPS"**
2. Isi nama server dan IP address
3. Klik **"Generate Agent Command"**
4. Copy command yang muncul, lalu jalankan di VPS target:

```bash
curl -sSL https://your-domain.com/agent/install.sh | sudo bash -s -- https://your-domain.com YOUR_TOKEN
```

Agent akan otomatis:
- Detect Python3 → pakai Python agent (recommended). Tidak ada? fallback ke Bash agent
- Register sebagai systemd service (`vps-agent.service`)
- Mulai kirim metrics setiap 5 detik
- Stream log files (syslog, auth.log) secara real-time

### Cek Status Agent

```bash
sudo systemctl status vps-agent.service
```

### Agent Log

```bash
sudo journalctl -u vps-agent.service -f
```

### Uninstall Agent

```bash
sudo systemctl stop vps-agent.service
sudo systemctl disable vps-agent.service
sudo rm /etc/systemd/system/vps-agent.service
sudo rm -rf /opt/vps-agent /etc/vps-agent
sudo systemctl daemon-reload
```

### Konfigurasi Agent

Config file ada di `/etc/vps-agent/config.json`:

```json
{
  "api_url": "https://your-domain.com",
  "agent_token": "YOUR_TOKEN",
  "log_files": ["/var/log/syslog", "/var/log/auth.log"]
}
```

Tambahkan log file yang mau di-monitor ke array `log_files`, lalu restart agent:

```bash
sudo systemctl restart vps-agent.service
```

---

## Usage

### Dashboard

Setelah login, dashboard menampilkan semua server yang terdaftar dengan:
- Status online/offline/pending
- Hardware specs (OS, CPU cores, RAM, Disk)
- Real-time gauge: CPU, RAM, Disk usage
- Load Average (1m / 5m / 15m)
- Metrics History — chart trend 24 jam

### Live Logs

Klik icon **dokumen** di server card untuk membuka log viewer:
- Real-time log streaming via WebSocket
- Filter by level (info, warning, error, critical)
- Filter by source file
- Auto-scroll toggle

### Process List

Klik tombol **"Processes"** di server card:
- Top 15 proses sorted by CPU usage
- Kolom: PID, User, CPU%, MEM%, Command
- Update real-time setiap 5 detik

### Web Terminal

Klik icon **terminal** di server card:
1. Pertama kali — isi SSH credentials (username, port, password/private key)
2. Klik "Save & Connect"
3. Terminal langsung terbuka di browser

Credentials disimpan encrypted di database.

### Alert Rules

Buka halaman **Alerts** dari sidebar:
1. Klik **"Add Rule"**
2. Pilih server (atau "All My Servers")
3. Pilih metric (CPU / RAM / Disk)
4. Set condition (misal: `> 80%`)
5. Masukkan nomor WhatsApp (dengan kode negara, misal `6281234567890`)
6. Set cooldown (berapa menit jeda antar notif)
7. Klik **"Create Rule"**

Saat threshold terlewati, kamu akan terima pesan WhatsApp otomatis.

### User Management (Admin)

Admin bisa akses menu **Users** di sidebar:
- Tambah user baru
- Set role (admin / user)
- Assign server ke user tertentu
- Edit / hapus user

---

## API Reference

### Agent API

Semua agent endpoint menggunakan header `X-Agent-Token` untuk autentikasi.

#### Handshake

```
POST /api/v1/agent/handshake
```

```json
{
  "os_info": "Ubuntu 24.04 LTS",
  "cpu_cores": 4,
  "ram_total": 8.0,
  "disk_total": 80.0
}
```

#### Report Metrics

```
POST /api/v1/agent/metrics
```

```json
{
  "cpu_usage": 45.2,
  "ram_usage": 62.8,
  "disk_usage": 31.5,
  "uptime": "15d 3h 42m",
  "load_avg_1": 1.25,
  "load_avg_5": 0.98,
  "load_avg_15": 0.75,
  "processes": [
    {
      "pid": 1234,
      "user": "www-data",
      "cpu": 12.5,
      "mem": 8.3,
      "command": "php-fpm: pool www"
    }
  ]
}
```

#### Report Logs

```
POST /api/v1/agent/logs
```

```json
{
  "logs": [
    {
      "source_file": "/var/log/syslog",
      "level": "error",
      "message": "Out of memory: Killed process 1234",
      "logged_at": "2026-05-28T10:30:00Z"
    }
  ]
}
```

Rate limit: 30 request/menit per token.

---

## Development

Untuk development lokal, jalankan semua service sekaligus:

```bash
composer dev
```

Ini akan menjalankan secara concurrent:
- `php artisan serve` — Laravel dev server
- `php artisan queue:listen` — Queue worker
- `php artisan pail` — Log viewer
- `npm run dev` — Vite dev server
- `php artisan reverb:start` — WebSocket server

---

## Contributing

Contributions welcome! Silakan buka issue atau submit PR.

1. Fork repo ini
2. Buat branch fitur (`git checkout -b feature/fitur-baru`)
3. Commit perubahan (`git commit -m 'Add fitur baru'`)
4. Push ke branch (`git push origin feature/fitur-baru`)
5. Buka Pull Request

---

## License

[MIT License](LICENSE)

---

<p align="center">
  Built with Laravel, Vue.js, and WebSocket.<br>
  <a href="https://github.com/idlanyor/server-manager">Give it a star</a> if you find it useful!
</p>
