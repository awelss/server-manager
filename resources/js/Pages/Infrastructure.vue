<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  servers: { type: Array, required: true },
});

const onlineCount = computed(() => props.servers.filter(s => s.is_online).length);
const warningCount = computed(() => props.servers.filter(hasWarning).length);

let poll = null;
onMounted(() => {
  poll = setInterval(() => {
    router.reload({ only: ['servers'], preserveScroll: true, preserveState: true });
  }, 30000);
});
onUnmounted(() => poll && clearInterval(poll));

function hasWarning(server) {
  const m = server.latest_metric || {};
  if (!server.is_online) return true;
  if ((m.cpu_steal || 0) >= 10 || (m.cpu_iowait || 0) >= 10 || (m.zombie_processes || 0) >= 10) return true;
  if ((m.disk_usage || 0) >= 85 || (m.inode_usage || 0) >= 85 || (m.swap_usage || 0) >= 70) return true;
  if ((server.service_status || []).some(x => x.state !== 'active')) return true;
  if ((server.docker_status || []).some(x => x.state !== 'running')) return true;
  if (server.backup_status?.status === 'stale' || server.backup_status?.status === 'missing') return true;
  if ((server.http_checks || []).some(x => !x.ok)) return true;
  return false;
}

function metricClass(value, warn = 60, danger = 85) {
  const n = Number(value || 0);
  if (n >= danger) return 'text-red-400';
  if (n >= warn) return 'text-amber-400';
  return 'text-emerald-400';
}

function stateClass(state) {
  if (state === 'active' || state === 'running' || state === 'ok') return 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20';
  if (state === 'unknown') return 'text-gray-400 bg-white/5 border-white/10';
  return 'text-red-400 bg-red-500/10 border-red-500/20';
}

function backupLabel(backup) {
  if (!backup) return 'No data';
  if (backup.status === 'ok') return `OK · ${Math.round((backup.age_minutes || 0) / 60)}h ago`;
  if (backup.status === 'stale') return `Stale · ${Math.round((backup.age_minutes || 0) / 60)}h ago`;
  if (backup.status === 'missing') return 'No backup found';
  return 'Unknown';
}

function formatBytes(bytes) {
  const n = Number(bytes || 0);
  if (!n) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.min(Math.floor(Math.log(n) / Math.log(1024)), units.length - 1);
  return `${(n / Math.pow(1024, i)).toFixed(i >= 3 ? 1 : 0)} ${units[i]}`;
}
</script>

<template>
  <Head title="Infrastructure" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between w-full">
        <div>
          <h2 class="text-2xl font-extrabold tracking-wide text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-cyan-400">
            Infrastructure
          </h2>
          <p class="text-xs text-gray-500 mt-0.5">Advanced VPS health, services, Docker and backup freshness</p>
        </div>
        <div class="flex gap-2">
          <Link :href="route('dashboard')" class="px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-xs text-gray-300 hover:bg-white/10">Dashboard</Link>
          <Link :href="route('alerts.index')" class="px-3 py-2 rounded-lg bg-indigo-600 text-xs text-white hover:bg-indigo-500">Alerts</Link>
        </div>
      </div>
    </template>

    <div class="py-8 bg-slate-950/20 min-h-screen text-gray-200">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="glass-panel rounded-xl border border-white/5 p-4 bg-slate-900/30">
            <div class="text-2xs uppercase tracking-widest text-gray-500">Servers</div>
            <div class="text-2xl font-bold mt-1">{{ servers.length }}</div>
          </div>
          <div class="glass-panel rounded-xl border border-white/5 p-4 bg-slate-900/30">
            <div class="text-2xs uppercase tracking-widest text-gray-500">Online</div>
            <div class="text-2xl font-bold mt-1 text-emerald-400">{{ onlineCount }}</div>
          </div>
          <div class="glass-panel rounded-xl border border-white/5 p-4 bg-slate-900/30">
            <div class="text-2xs uppercase tracking-widest text-gray-500">Warnings</div>
            <div class="text-2xl font-bold mt-1" :class="warningCount ? 'text-amber-400' : 'text-emerald-400'">{{ warningCount }}</div>
          </div>
          <div class="glass-panel rounded-xl border border-white/5 p-4 bg-slate-900/30">
            <div class="text-2xs uppercase tracking-widest text-gray-500">Agent refresh</div>
            <div class="text-sm font-semibold mt-2 text-gray-300">Metrics ~5s · Health ~60s</div>
          </div>
        </div>

        <div v-if="servers.length === 0" class="glass-panel rounded-2xl p-10 text-center border border-white/5 bg-slate-900/30 text-gray-400">
          Register a VPS from Dashboard first.
        </div>

        <div v-for="server in servers" :key="server.id" class="glass-panel rounded-2xl border border-white/5 bg-slate-900/30 overflow-hidden">
          <div class="p-5 border-b border-white/5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full" :class="server.is_online ? 'bg-emerald-400' : 'bg-red-400'"></span>
                <h3 class="font-bold text-lg text-gray-100">{{ server.name }}</h3>
                <span class="text-2xs px-2 py-0.5 rounded border" :class="hasWarning(server) ? 'text-amber-400 bg-amber-500/10 border-amber-500/20' : 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20'">
                  {{ hasWarning(server) ? 'ATTENTION' : 'HEALTHY' }}
                </span>
              </div>
              <div class="text-xs text-gray-500 font-mono mt-1">{{ server.ip_address }} · {{ server.os_info || 'Awaiting agent' }}</div>
            </div>
            <div class="text-xs text-gray-500">{{ server.latest_metric?.uptime || '—' }} uptime</div>
          </div>

          <div class="p-5 space-y-6">
            <div>
              <div class="text-2xs uppercase tracking-widest text-gray-500 mb-3">Advanced metrics</div>
              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">CPU</div>
                  <div class="font-mono font-bold mt-1" :class="metricClass(server.latest_metric?.cpu_usage)">{{ server.latest_metric?.cpu_usage ?? '—' }}%</div>
                </div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">CPU Steal</div>
                  <div class="font-mono font-bold mt-1" :class="metricClass(server.latest_metric?.cpu_steal, 5, 10)">{{ server.latest_metric?.cpu_steal ?? '—' }}%</div>
                </div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">I/O Wait</div>
                  <div class="font-mono font-bold mt-1" :class="metricClass(server.latest_metric?.cpu_iowait, 5, 10)">{{ server.latest_metric?.cpu_iowait ?? '—' }}%</div>
                </div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">Load 1 / 5 / 15</div>
                  <div class="font-mono text-sm font-bold mt-1 text-cyan-300">{{ server.latest_metric?.load_1 ?? '—' }} / {{ server.latest_metric?.load_5 ?? '—' }} / {{ server.latest_metric?.load_15 ?? '—' }}</div>
                </div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">Swap</div>
                  <div class="font-mono font-bold mt-1" :class="metricClass(server.latest_metric?.swap_usage, 50, 70)">{{ server.latest_metric?.swap_usage ?? '—' }}%</div>
                </div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">Zombies / Processes</div>
                  <div class="font-mono font-bold mt-1" :class="(server.latest_metric?.zombie_processes || 0) >= 10 ? 'text-red-400' : 'text-gray-200'">{{ server.latest_metric?.zombie_processes ?? '—' }} / {{ server.latest_metric?.process_count ?? '—' }}</div>
                </div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">RAM</div>
                  <div class="font-mono font-bold mt-1" :class="metricClass(server.latest_metric?.ram_usage)">{{ server.latest_metric?.ram_usage ?? '—' }}%</div>
                </div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">Disk</div>
                  <div class="font-mono font-bold mt-1" :class="metricClass(server.latest_metric?.disk_usage)">{{ server.latest_metric?.disk_usage ?? '—' }}%</div>
                </div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">Disk Free</div>
                  <div class="font-mono font-bold mt-1 text-gray-200">{{ server.latest_metric?.disk_free_gb ?? '—' }} GB</div>
                </div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">Inodes</div>
                  <div class="font-mono font-bold mt-1" :class="metricClass(server.latest_metric?.inode_usage)">{{ server.latest_metric?.inode_usage ?? '—' }}%</div>
                </div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">Network RX</div>
                  <div class="font-mono text-sm font-bold mt-1 text-gray-200">{{ formatBytes(server.latest_metric?.network_rx_bytes) }}</div>
                </div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-3">
                  <div class="text-2xs text-gray-500">Network TX</div>
                  <div class="font-mono text-sm font-bold mt-1 text-gray-200">{{ formatBytes(server.latest_metric?.network_tx_bytes) }}</div>
                </div>
              </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-5">
              <div>
                <div class="text-2xs uppercase tracking-widest text-gray-500 mb-3">Services</div>
                <div class="space-y-2">
                  <div v-if="!(server.service_status || []).length" class="text-xs text-gray-600">No service snapshot yet.</div>
                  <div v-for="svc in server.service_status || []" :key="svc.name" class="flex items-center justify-between rounded-lg bg-slate-950/40 border border-white/5 px-3 py-2">
                    <span class="text-sm font-mono text-gray-300">{{ svc.name }}</span>
                    <span class="text-2xs uppercase font-bold px-2 py-0.5 rounded border" :class="stateClass(svc.state)">{{ svc.state }}</span>
                  </div>
                </div>
              </div>

              <div>
                <div class="text-2xs uppercase tracking-widest text-gray-500 mb-3">Backup</div>
                <div class="rounded-xl bg-slate-950/40 border border-white/5 p-4">
                  <div class="flex items-center justify-between gap-3">
                    <div>
                      <div class="text-sm font-semibold text-gray-200">{{ backupLabel(server.backup_status) }}</div>
                      <div class="text-2xs text-gray-600 mt-1 break-all">{{ server.backup_status?.latest_file || 'Configure backup_paths in the agent if needed.' }}</div>
                    </div>
                    <span class="text-2xs uppercase font-bold px-2 py-1 rounded border" :class="stateClass(server.backup_status?.status || 'unknown')">{{ server.backup_status?.status || 'unknown' }}</span>
                  </div>
                </div>

                <div class="text-2xs uppercase tracking-widest text-gray-500 mt-5 mb-3">HTTP health checks</div>
                <div class="space-y-2">
                  <div v-if="!(server.http_checks || []).length" class="text-xs text-gray-600">No URLs configured in agent health_urls.</div>
                  <div v-for="check in server.http_checks || []" :key="check.url" class="flex items-center justify-between gap-3 rounded-lg bg-slate-950/40 border border-white/5 px-3 py-2">
                    <span class="text-xs font-mono text-gray-400 truncate">{{ check.url }}</span>
                    <span class="text-2xs font-mono whitespace-nowrap" :class="check.ok ? 'text-emerald-400' : 'text-red-400'">{{ check.status_code }} · {{ check.latency_ms }}ms</span>
                  </div>
                </div>
              </div>
            </div>

            <div>
              <div class="text-2xs uppercase tracking-widest text-gray-500 mb-3">Docker containers</div>
              <div v-if="!(server.docker_status || []).length" class="text-xs text-gray-600">Docker unavailable or no containers found.</div>
              <div v-else class="overflow-x-auto rounded-xl border border-white/5">
                <table class="w-full text-xs">
                  <thead class="bg-slate-950/60 text-gray-500 uppercase tracking-wider">
                    <tr><th class="text-left px-3 py-2">Container</th><th class="text-left px-3 py-2">State</th><th class="text-right px-3 py-2">CPU</th><th class="text-right px-3 py-2">Memory</th><th class="text-right px-3 py-2">Restarts</th></tr>
                  </thead>
                  <tbody class="divide-y divide-white/5">
                    <tr v-for="c in server.docker_status || []" :key="c.name" class="bg-slate-950/30">
                      <td class="px-3 py-2 font-mono text-gray-300">{{ c.name }}</td>
                      <td class="px-3 py-2"><span class="px-2 py-0.5 rounded border text-2xs uppercase" :class="stateClass(c.state)">{{ c.state }}</span></td>
                      <td class="px-3 py-2 text-right font-mono text-gray-300">{{ c.cpu_percent ?? 0 }}%</td>
                      <td class="px-3 py-2 text-right font-mono text-gray-300">{{ c.memory_percent ?? 0 }}%</td>
                      <td class="px-3 py-2 text-right font-mono" :class="(c.restart_count || 0) > 3 ? 'text-amber-400' : 'text-gray-300'">{{ c.restart_count ?? 0 }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
