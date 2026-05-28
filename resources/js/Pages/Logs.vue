<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';

const props = defineProps({
  server: Object,
  logs: Object,
});

const localLogs = ref([...(props.logs.data || [])]);
const filterLevel = ref('all');
const filterSource = ref('all');
const autoScroll = ref(true);
const logContainer = ref(null);

// Get unique source files from logs
const sourceFiles = computed(() => {
  const sources = new Set(localLogs.value.map(l => l.source_file));
  return [...sources].sort();
});

const filteredLogs = computed(() => {
  return localLogs.value.filter(log => {
    if (filterLevel.value !== 'all' && log.level !== filterLevel.value) return false;
    if (filterSource.value !== 'all' && log.source_file !== filterSource.value) return false;
    return true;
  });
});

const levelColors = {
  debug: 'text-gray-500',
  info: 'text-blue-400',
  notice: 'text-cyan-400',
  warning: 'text-amber-400',
  error: 'text-red-400',
  critical: 'text-red-500 font-bold',
  alert: 'text-red-500 font-bold',
  emergency: 'text-red-600 font-extrabold',
};

const levelBadgeColors = {
  debug: 'bg-gray-500/10 text-gray-400 border-gray-500/20',
  info: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
  notice: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
  warning: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
  error: 'bg-red-500/10 text-red-400 border-red-500/20',
  critical: 'bg-red-500/20 text-red-500 border-red-500/30',
  alert: 'bg-red-500/20 text-red-500 border-red-500/30',
  emergency: 'bg-red-500/30 text-red-600 border-red-500/40',
};

const scrollToBottom = () => {
  if (autoScroll.value && logContainer.value) {
    nextTick(() => {
      logContainer.value.scrollTop = logContainer.value.scrollHeight;
    });
  }
};

const handleScroll = () => {
  if (!logContainer.value) return;
  const { scrollTop, scrollHeight, clientHeight } = logContainer.value;
  autoScroll.value = scrollHeight - scrollTop - clientHeight < 50;
};

const formatTime = (dateStr) => {
  const d = new Date(dateStr);
  return d.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const formatDate = (dateStr) => {
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const shortSource = (path) => {
  const parts = path.split('/');
  return parts[parts.length - 1];
};

onMounted(() => {
  scrollToBottom();

  // Subscribe to real-time log updates
  window.Echo.private(`server-logs.${props.server.id}`)
    .listen('LogReceived', (e) => {
      localLogs.value.unshift(e);
      // Keep max 500 logs in memory
      if (localLogs.value.length > 500) {
        localLogs.value = localLogs.value.slice(0, 500);
      }
      scrollToBottom();
    });
});

onUnmounted(() => {
  window.Echo.leave(`server-logs.${props.server.id}`);
});
</script>

<template>
  <Head :title="`Logs — ${server.name}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center w-full">
        <div>
          <div class="flex items-center gap-3">
            <a :href="route('dashboard')" class="text-gray-500 hover:text-gray-300 transition-colors">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </a>
            <div>
              <h2 class="text-2xl font-extrabold tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-cyan-400 font-sans">
                Live Logs
              </h2>
              <p class="text-xs text-gray-500 mt-0.5 font-mono">{{ server.name }} · {{ server.ip_address }}</p>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="flex items-center gap-3">
          <select
            v-model="filterLevel"
            class="bg-slate-800/80 border border-white/10 text-gray-300 text-xs rounded-lg px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500"
          >
            <option value="all">All Levels</option>
            <option value="debug">Debug</option>
            <option value="info">Info</option>
            <option value="notice">Notice</option>
            <option value="warning">Warning</option>
            <option value="error">Error</option>
            <option value="critical">Critical</option>
          </select>

          <select
            v-model="filterSource"
            class="bg-slate-800/80 border border-white/10 text-gray-300 text-xs rounded-lg px-3 py-1.5 focus:ring-indigo-500 focus:border-indigo-500"
          >
            <option value="all">All Sources</option>
            <option v-for="src in sourceFiles" :key="src" :value="src">{{ shortSource(src) }}</option>
          </select>

          <button
            @click="autoScroll = !autoScroll"
            class="px-3 py-1.5 text-xs font-mono rounded-lg border transition-all"
            :class="autoScroll
              ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
              : 'bg-white/5 text-gray-500 border-white/10'"
          >
            {{ autoScroll ? 'Auto-scroll ON' : 'Auto-scroll OFF' }}
          </button>
        </div>
      </div>
    </template>

    <div class="py-6 bg-slate-950/20 min-h-screen">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="glass-panel rounded-2xl border border-white/5 overflow-hidden">
          <!-- Log Stream -->
          <div
            ref="logContainer"
            @scroll="handleScroll"
            class="h-[calc(100vh-220px)] overflow-y-auto font-mono text-xs leading-relaxed custom-scrollbar"
          >
            <!-- Empty State -->
            <div v-if="filteredLogs.length === 0" class="flex flex-col items-center justify-center h-full text-gray-500">
              <svg class="w-12 h-12 mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <p>No log entries yet. Waiting for agent...</p>
            </div>

            <!-- Log Entries -->
            <div v-else class="divide-y divide-white/3">
              <div
                v-for="log in filteredLogs"
                :key="log.id"
                class="px-4 py-2 hover:bg-white/2 transition-colors flex items-start gap-3"
              >
                <!-- Timestamp -->
                <span class="text-gray-600 whitespace-nowrap shrink-0">
                  {{ formatDate(log.logged_at) }} {{ formatTime(log.logged_at) }}
                </span>

                <!-- Level Badge -->
                <span
                  class="px-1.5 py-0.5 rounded text-2xs font-bold uppercase tracking-wider border shrink-0 w-16 text-center"
                  :class="levelBadgeColors[log.level] || levelBadgeColors.info"
                >
                  {{ log.level }}
                </span>

                <!-- Source -->
                <span class="text-indigo-400/60 shrink-0 w-20 truncate" :title="log.source_file">
                  {{ shortSource(log.source_file) }}
                </span>

                <!-- Message -->
                <span :class="levelColors[log.level] || 'text-gray-300'" class="break-all">
                  {{ log.message }}
                </span>
              </div>
            </div>
          </div>

          <!-- Footer Status Bar -->
          <div class="px-4 py-2 border-t border-white/5 flex justify-between items-center text-2xs text-gray-600 font-mono bg-slate-900/50">
            <span>{{ filteredLogs.length }} entries shown</span>
            <span class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 pulse-active"></span>
              Live streaming
            </span>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
