<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, reactive, onMounted, onUnmounted, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import ServerModal from '@/Components/ServerModal.vue';

const props = defineProps({
  servers: {
    type: Array,
    required: true
  },
  apiBaseUrl: {
    type: String,
    required: true
  }
});

// Local reactive copy of servers for real-time updates
const localServers = ref(JSON.parse(JSON.stringify(props.servers)));

// Sync when Inertia reloads the prop (e.g. after adding/deleting a server)
watch(() => props.servers, (newVal) => {
  localServers.value = JSON.parse(JSON.stringify(newVal));
  subscribeToChannels();
}, { deep: true });

const isAddModalOpen = ref(false);
const activeHistoryServerId = ref(null);
const historyData = ref([]);
const isHistoryLoading = ref(false);

// Subscribe to Echo channels for real-time metric updates
let subscribedChannels = [];

const subscribeToChannels = () => {
  // Leave old channels
  subscribedChannels.forEach(ch => window.Echo.leave(ch));
  subscribedChannels = [];

  localServers.value.forEach(server => {
    const channelName = `server.${server.id}`;
    subscribedChannels.push(channelName);

    window.Echo.private(channelName)
      .listen('MetricsUpdated', (e) => {
        const s = localServers.value.find(s => s.id === e.server_id);
        if (s) {
          s.latest_metric = {
            ...(s.latest_metric || {}),
            cpu_usage: e.cpu_usage,
            ram_usage: e.ram_usage,
            disk_usage: e.disk_usage,
            uptime: e.uptime,
          };
          s.is_online = true;
        }
      });
  });
};

// Fallback: slow poll every 30s for status sync (offline detection, etc.)
let pollInterval = null;

onMounted(() => {
  subscribeToChannels();
  pollInterval = setInterval(() => {
    router.reload({
      only: ['servers'],
      preserveScroll: true,
      preserveState: true
    });
  }, 30000);
});

onUnmounted(() => {
  subscribedChannels.forEach(ch => window.Echo.leave(ch));
  subscribedChannels = [];
  if (pollInterval) clearInterval(pollInterval);
});

// Manage Server Deletion
const deleteForm = useForm({});
const deleteServer = (serverId, name) => {
  if (confirm(`Are you absolutely sure you want to delete server "${name}"? This will erase all its recorded metrics logs.`)) {
    deleteForm.delete(route('servers.destroy', serverId), {
      preserveScroll: true
    });
  }
};

// Fetch Historical Metrics on-demand
const toggleHistory = async (serverId) => {
  if (activeHistoryServerId.value === serverId) {
    activeHistoryServerId.value = null;
    historyData.value = [];
    return;
  }
  
  activeHistoryServerId.value = serverId;
  isHistoryLoading.value = true;
  historyData.value = [];

  try {
    const response = await fetch(`/dashboard/servers/${serverId}/metrics`);
    const data = await response.json();
    if (data.status === 'success') {
      historyData.value = data.metrics;
    }
  } catch (err) {
    console.error("Error loading historical metrics: ", err);
  } finally {
    isHistoryLoading.value = false;
  }
};

// SVG Circular Gauge Math
const RADIUS = 40;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const strokeDashoffset = (percentage) => {
  const cleanPct = Math.max(0, Math.min(100, percentage || 0));
  return CIRCUMFERENCE - (cleanPct / 100) * CIRCUMFERENCE;
};

// Determine Color Class based on usage
const getGaugeColor = (pct) => {
  if (pct >= 85) return 'stroke-red-500 shadow-red-500/50 glow-danger';
  if (pct >= 60) return 'stroke-amber-500 shadow-amber-500/50 glow-warning';
  return 'stroke-emerald-400 shadow-emerald-400/50 glow-success';
};

// Stat bar gradient color based on usage
const getBarGradient = (pct) => {
  if (pct >= 85) return 'from-red-600 to-rose-400';
  if (pct >= 60) return 'from-amber-500 to-yellow-300';
  return 'from-emerald-500 to-cyan-400';
};

// Stat bar glow class
const getBarGlow = (pct) => {
  if (pct >= 85) return 'shadow-red-500/40';
  if (pct >= 60) return 'shadow-amber-500/40';
  return 'shadow-emerald-500/40';
};

// Usage status label
const getUsageStatus = (pct) => {
  if (pct >= 85) return { label: 'Critical', cls: 'text-red-400' };
  if (pct >= 60) return { label: 'High', cls: 'text-amber-400' };
  return { label: 'Normal', cls: 'text-emerald-400' };
};

// Calculate SVG Polyline Points for History Chart
const buildPoints = (metrics, key, width, height) => {
  if (!metrics || metrics.length < 2) return '';
  const maxVal = 100;
  const padding = 10;
  const graphWidth = width - padding * 2;
  const graphHeight = height - padding * 2;

  return metrics.map((m, index) => {
    const x = padding + (index / (metrics.length - 1)) * graphWidth;
    const val = Math.max(0, Math.min(maxVal, m[key] || 0));
    const y = padding + graphHeight - (val / maxVal) * graphHeight;
    return `${x},${y}`;
  }).join(' ');
};

const buildAreaPoints = (metrics, key, width, height) => {
  const points = buildPoints(metrics, key, width, height);
  if (!points) return '';
  const padding = 10;
  const graphWidth = width - padding * 2;
  return `${padding},${height - padding} ${points} ${padding + graphWidth},${height - padding}`;
};
</script>

<template>
  <Head title="VPS Control Hub" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center w-full">
        <div>
          <h2 class="text-2xl font-extrabold tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-cyan-400 font-sans">
            VPS Control Hub
          </h2>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Centralized Real-Time Agent-Based VPS Monitoring</p>
        </div>
        <button 
          @click="isAddModalOpen = true"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold tracking-wide shadow-lg border border-white/10 glow-primary transition-all flex items-center gap-1.5"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          Register VPS
        </button>
      </div>
    </template>

    <div class="py-10 bg-slate-950/20 min-h-screen text-gray-200">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        
        <!-- Empty State -->
        <div v-if="localServers.length === 0" class="glass-panel text-center p-16 rounded-2xl border border-white/5 bg-slate-900/40">
          <div class="inline-flex p-4 rounded-full bg-indigo-500/10 text-indigo-400 mb-4 border border-indigo-500/20 glow-primary">
            <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-200">No VPS Registered</h3>
          <p class="text-gray-400 mt-2 max-w-md mx-auto text-sm leading-relaxed">
            Get started by registering your first VPS. We will generate a lightweight monitoring script for you to run.
          </p>
          <button 
            @click="isAddModalOpen = true"
            class="mt-6 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-bold glow-primary border border-white/5 transition-all"
          >
            Add Server
          </button>
        </div>

        <!-- Servers Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div 
            v-for="server in localServers" 
            :key="server.id"
            class="glass-panel rounded-2xl p-6 border border-white/5 flex flex-col justify-between"
            :class="server.is_online ? 'bg-slate-900/30' : 'bg-slate-950/50'"
          >
            <!-- Card Header -->
            <div>
              <div class="flex justify-between items-start mb-4">
                <div>
                  <div class="flex items-center gap-2">
                    <span 
                      class="w-2.5 h-2.5 rounded-full"
                      :class="{
                        'pulse-active': server.is_online,
                        'pulse-pending text-amber-500': !server.is_online && server.status === 'pending',
                        'pulse-offline text-red-500': !server.is_online && server.status !== 'pending'
                      }"
                    >●</span>
                    <h3 class="text-lg font-bold text-gray-100 font-sans">{{ server.name }}</h3>
                  </div>
                  <span class="text-xs text-gray-500 font-mono tracking-wider">{{ server.ip_address }}</span>
                </div>
                
                <!-- Status Badge -->
                <span 
                  class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold uppercase tracking-widest border"
                  :class="{
                    'bg-emerald-500/10 text-emerald-400 border-emerald-500/20': server.is_online,
                    'bg-amber-500/10 text-amber-400 border-amber-500/20': !server.is_online && server.status === 'pending',
                    'bg-red-500/10 text-red-400 border-red-500/20': !server.is_online && server.status !== 'pending'
                  }"
                >
                  {{ server.is_online ? 'Online' : (server.status === 'pending' ? 'Pending' : 'Offline') }}
                </span>
              </div>

              <!-- VPS Hardware Specs -->
              <div class="grid grid-cols-2 gap-3 py-3 border-y border-white/5 mb-6 text-xs text-gray-400 font-mono">
                <div>
                  <span class="block text-3xs text-gray-600 uppercase tracking-widest">OS System</span>
                  <span class="text-gray-300">{{ server.os_info || 'Awaiting agent...' }}</span>
                </div>
                <div>
                  <span class="block text-3xs text-gray-600 uppercase tracking-widest">System Uptime</span>
                  <span class="text-gray-300">{{ server.latest_metric?.uptime || '—' }}</span>
                </div>
                <div>
                  <span class="block text-3xs text-gray-600 uppercase tracking-widest">CPU Configuration</span>
                  <span class="text-gray-300">{{ server.cpu_cores ? `${server.cpu_cores} Cores` : '—' }}</span>
                </div>
                <div>
                  <span class="block text-3xs text-gray-600 uppercase tracking-widest">RAM & Disk Specs</span>
                  <span class="text-gray-300">
                    {{ server.ram_total ? `${server.ram_total} GB` : '—' }} / {{ server.disk_total ? `${server.disk_total} GB` : '—' }}
                  </span>
                </div>
              </div>

              <!-- Resource Stats (Only if active/online or has metrics) -->
              <div v-if="server.latest_metric" class="space-y-3 mb-6">

                <!-- CPU Stat Bar -->
                <div class="stat-bar-card group">
                  <div class="flex items-center gap-2.5 mb-2">
                    <!-- CPU Icon -->
                    <div class="stat-icon-wrap bg-sky-500/10 text-sky-400 border-sky-500/20">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H7a2 2 0 00-2 2v2M9 3h6M9 3v2m6-2h2a2 2 0 012 2v2m0 0V7m0 0h-2M3 9v6m0 0v2a2 2 0 002 2h2m-4-4h2m14-8v6m0 0v2a2 2 0 01-2 2h-2m4-4h-2M9 21h6m-6 0v-2m6 2v-2M9 19H7a2 2 0 01-2-2v-2m4 4h6" />
                      </svg>
                    </div>
                    <span class="text-xs font-semibold text-gray-300 tracking-wide">CPU Usage</span>
                    <div class="ml-auto flex items-center gap-2">
                      <span class="text-xs font-mono" :class="getUsageStatus(server.latest_metric.cpu_usage).cls">{{ getUsageStatus(server.latest_metric.cpu_usage).label }}</span>
                      <span class="text-sm font-bold font-mono text-white">{{ server.latest_metric.cpu_usage }}<span class="text-gray-500 text-xs">%</span></span>
                    </div>
                  </div>
                  <div class="stat-track">
                    <div 
                      class="stat-fill bg-gradient-to-r shadow-sm"
                      :class="[getBarGradient(server.latest_metric.cpu_usage), getBarGlow(server.latest_metric.cpu_usage)]"
                      :style="{ width: (server.latest_metric.cpu_usage || 0) + '%' }"
                    ></div>
                  </div>
                </div>

                <!-- RAM Stat Bar -->
                <div class="stat-bar-card group">
                  <div class="flex items-center gap-2.5 mb-2">
                    <!-- RAM Icon -->
                    <div class="stat-icon-wrap bg-violet-500/10 text-violet-400 border-violet-500/20">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                      </svg>
                    </div>
                    <span class="text-xs font-semibold text-gray-300 tracking-wide">Memory</span>
                    <div class="ml-auto flex items-center gap-2">
                      <span class="text-xs font-mono" :class="getUsageStatus(server.latest_metric.ram_usage).cls">{{ getUsageStatus(server.latest_metric.ram_usage).label }}</span>
                      <span class="text-sm font-bold font-mono text-white">{{ server.latest_metric.ram_usage }}<span class="text-gray-500 text-xs">%</span></span>
                    </div>
                  </div>
                  <div class="stat-track">
                    <div 
                      class="stat-fill bg-gradient-to-r shadow-sm"
                      :class="[getBarGradient(server.latest_metric.ram_usage), getBarGlow(server.latest_metric.ram_usage)]"
                      :style="{ width: (server.latest_metric.ram_usage || 0) + '%' }"
                    ></div>
                  </div>
                </div>

                <!-- Disk Stat Bar -->
                <div class="stat-bar-card group">
                  <div class="flex items-center gap-2.5 mb-2">
                    <!-- Disk Icon -->
                    <div class="stat-icon-wrap bg-orange-500/10 text-orange-400 border-orange-500/20">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                      </svg>
                    </div>
                    <span class="text-xs font-semibold text-gray-300 tracking-wide">Storage</span>
                    <div class="ml-auto flex items-center gap-2">
                      <span class="text-xs font-mono" :class="getUsageStatus(server.latest_metric.disk_usage).cls">{{ getUsageStatus(server.latest_metric.disk_usage).label }}</span>
                      <span class="text-sm font-bold font-mono text-white">{{ server.latest_metric.disk_usage }}<span class="text-gray-500 text-xs">%</span></span>
                    </div>
                  </div>
                  <div class="stat-track">
                    <div 
                      class="stat-fill bg-gradient-to-r shadow-sm"
                      :class="[getBarGradient(server.latest_metric.disk_usage), getBarGlow(server.latest_metric.disk_usage)]"
                      :style="{ width: (server.latest_metric.disk_usage || 0) + '%' }"
                    ></div>
                  </div>
                </div>

              </div>

              <!-- Uninstalled/Pending warning -->
              <div v-else class="flex items-center gap-3 p-4 bg-white/2 border border-white/5 rounded-xl mb-6">
                <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center border border-white/5">
                  <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                  </svg>
                </div>
                <div>
                  <p class="text-xs font-semibold text-gray-400">No metrics yet</p>
                  <p class="text-2xs text-gray-600 mt-0.5">Run the setup command on your VPS to start reporting.</p>
                </div>
              </div>
            </div>

            <!-- Expandable History Charts Panel -->
            <div v-if="activeHistoryServerId === server.id" class="w-full py-4 border-t border-white/5 mb-4 transition-all">
              <div class="flex justify-between items-center mb-3">
                <h4 class="text-xs font-bold text-indigo-400 font-mono tracking-wider">Metrics History (24h Trend)</h4>
                <button @click="toggleHistory(server.id)" class="text-2xs text-gray-500 hover:text-white font-mono uppercase tracking-widest">Close</button>
              </div>

              <!-- Chart Loader -->
              <div v-if="isHistoryLoading" class="flex justify-center py-10">
                <svg class="animate-spin h-6 w-6 text-indigo-500" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>

              <!-- Empty history data -->
              <div v-else-if="historyData.length < 2" class="text-center py-6 text-xs text-gray-500">
                Not enough metric data points collected yet. Wait for a few minutes for the trend line to generate.
              </div>

              <!-- SVG Interactive Line Graph -->
              <div v-else class="space-y-4">
                <div>
                  <div class="flex justify-between text-3xs text-gray-500 font-mono mb-1">
                    <span>CPU USAGE TREND</span>
                    <span class="text-gray-300 font-semibold">{{ historyData[historyData.length - 1]?.cpu_usage }}%</span>
                  </div>
                  <!-- SVG Polyline Chart -->
                  <svg viewBox="0 0 350 70" class="w-full h-16 rounded bg-slate-950/50 border border-white/5">
                    <defs>
                      <linearGradient id="cpu-grad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.3"></stop>
                        <stop offset="100%" stop-color="#3b82f6" stop-opacity="0"></stop>
                      </linearGradient>
                    </defs>
                    <!-- Area under polyline -->
                    <polygon :points="buildAreaPoints(historyData, 'cpu_usage', 350, 70)" fill="url(#cpu-grad)"></polygon>
                    <!-- Line -->
                    <polyline 
                      fill="none" 
                      stroke="#3b82f6" 
                      stroke-width="1.8" 
                      stroke-linecap="round"
                      :points="buildPoints(historyData, 'cpu_usage', 350, 70)"
                    ></polyline>
                  </svg>
                </div>

                <div>
                  <div class="flex justify-between text-3xs text-gray-500 font-mono mb-1">
                    <span>MEMORY USAGE TREND</span>
                    <span class="text-gray-300 font-semibold">{{ historyData[historyData.length - 1]?.ram_usage }}%</span>
                  </div>
                  <svg viewBox="0 0 350 70" class="w-full h-16 rounded bg-slate-950/50 border border-white/5">
                    <defs>
                      <linearGradient id="ram-grad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.3"></stop>
                        <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0"></stop>
                      </linearGradient>
                    </defs>
                    <polygon :points="buildAreaPoints(historyData, 'ram_usage', 350, 70)" fill="url(#ram-grad)"></polygon>
                    <polyline 
                      fill="none" 
                      stroke="#8b5cf6" 
                      stroke-width="1.8" 
                      stroke-linecap="round"
                      :points="buildPoints(historyData, 'ram_usage', 350, 70)"
                    ></polyline>
                  </svg>
                </div>
              </div>
            </div>

            <!-- Card Actions Footer -->
            <div class="flex justify-between items-center border-t border-white/5 pt-4">
              <button 
                @click="toggleHistory(server.id)"
                :disabled="!server.latest_metric"
                class="px-3.5 py-1.5 bg-white/5 hover:bg-indigo-600 disabled:opacity-30 disabled:hover:bg-white/5 border border-white/5 rounded-lg text-2xs font-bold font-mono uppercase tracking-widest text-indigo-300 hover:text-white transition-all"
              >
                Metrics History
              </button>

              <div class="flex gap-2">
                <!-- Logs Button -->
                <Link
                  :href="route('servers.logs', server.id)"
                  class="p-1.5 bg-white/2 hover:bg-indigo-500/10 border border-white/5 hover:border-indigo-500/20 text-gray-500 hover:text-indigo-400 rounded-lg transition-all"
                  title="View Logs"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </Link>
                <!-- Delete Button -->
                <button 
                  @click="deleteServer(server.id, server.name)"
                  class="p-1.5 bg-white/2 hover:bg-red-500/10 border border-white/5 hover:border-red-500/20 text-gray-500 hover:text-red-400 rounded-lg transition-all"
                  title="Remove Server"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Modals -->
    <ServerModal 
      :show="isAddModalOpen" 
      :api-base-url="apiBaseUrl"
      @close="isAddModalOpen = false"
    />
  </AuthenticatedLayout>
</template>
