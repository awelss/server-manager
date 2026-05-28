<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  rules: Array,
  servers: Array,
});

const showForm = ref(false);

const form = useForm({
  server_id: '',
  metric: 'cpu',
  operator: '>',
  threshold: 80,
  cooldown_minutes: 15,
  whatsapp_number: '',
});

const submit = () => {
  form.post(route('alerts.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      showForm.value = false;
    },
  });
};

const toggleEnabled = (rule) => {
  router.patch(route('alerts.update', rule.id), {
    enabled: !rule.enabled,
  }, {
    preserveScroll: true,
  });
};

const deleteRule = (rule) => {
  if (confirm('Delete this alert rule?')) {
    router.delete(route('alerts.destroy', rule.id), {
      preserveScroll: true,
    });
  }
};

const metricLabels = { cpu: 'CPU', ram: 'RAM', disk: 'Disk' };
const metricColors = {
  cpu: 'text-blue-400',
  ram: 'text-purple-400',
  disk: 'text-amber-400',
};

const formatLastTriggered = (date) => {
  if (!date) return 'Never';
  const d = new Date(date);
  const now = new Date();
  const diffMs = now - d;
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  return `${Math.floor(diffHours / 24)}d ago`;
};
</script>

<template>
  <Head title="Alert Rules" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center w-full">
        <div>
          <h2 class="text-2xl font-extrabold tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-cyan-400 font-sans">
            Alert Rules
          </h2>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Configure threshold-based WhatsApp notifications</p>
        </div>
        <button
          @click="showForm = !showForm"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold tracking-wide shadow-lg border border-white/10 glow-primary transition-all flex items-center gap-1.5"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          Add Rule
        </button>
      </div>
    </template>

    <div class="py-10 bg-slate-950/20 min-h-screen text-gray-200">
      <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- Add Rule Form -->
        <div v-if="showForm" class="glass-panel rounded-2xl p-6 border border-white/5 bg-slate-900/30">
          <h3 class="text-sm font-bold text-gray-200 mb-4 font-mono tracking-wider uppercase">New Alert Rule</h3>
          <form @submit.prevent="submit" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <!-- Server -->
              <div>
                <label class="block text-xs text-gray-500 mb-1 font-mono">Server</label>
                <select v-model="form.server_id" class="w-full bg-slate-800/80 border border-white/10 text-gray-300 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                  <option value="">All My Servers</option>
                  <option v-for="s in servers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>

              <!-- Metric -->
              <div>
                <label class="block text-xs text-gray-500 mb-1 font-mono">Metric</label>
                <select v-model="form.metric" class="w-full bg-slate-800/80 border border-white/10 text-gray-300 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                  <option value="cpu">CPU Usage</option>
                  <option value="ram">RAM Usage</option>
                  <option value="disk">Disk Usage</option>
                </select>
              </div>

              <!-- Operator + Threshold -->
              <div>
                <label class="block text-xs text-gray-500 mb-1 font-mono">Condition</label>
                <div class="flex gap-2">
                  <select v-model="form.operator" class="w-20 bg-slate-800/80 border border-white/10 text-gray-300 text-sm rounded-lg px-2 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value=">">></option>
                    <option value=">=">>=</option>
                    <option value="<"><</option>
                    <option value="<="><=</option>
                  </select>
                  <div class="relative flex-1">
                    <input v-model="form.threshold" type="number" min="0" max="100" step="0.1"
                      class="w-full bg-slate-800/80 border border-white/10 text-gray-300 text-sm rounded-lg px-3 py-2 pr-8 focus:ring-indigo-500 focus:border-indigo-500" />
                    <span class="absolute right-3 top-2 text-gray-500 text-sm">%</span>
                  </div>
                </div>
              </div>

              <!-- Cooldown -->
              <div>
                <label class="block text-xs text-gray-500 mb-1 font-mono">Cooldown (minutes)</label>
                <input v-model="form.cooldown_minutes" type="number" min="1" max="1440"
                  class="w-full bg-slate-800/80 border border-white/10 text-gray-300 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" />
              </div>

              <!-- WhatsApp Number -->
              <div class="col-span-2">
                <label class="block text-xs text-gray-500 mb-1 font-mono">WhatsApp Number (with country code)</label>
                <input v-model="form.whatsapp_number" type="text" placeholder="6281234567890"
                  class="w-full bg-slate-800/80 border border-white/10 text-gray-300 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" />
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="showForm = false"
                class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 text-gray-400 rounded-lg text-sm transition-all">
                Cancel
              </button>
              <button type="submit" :disabled="form.processing"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold glow-primary border border-white/10 transition-all disabled:opacity-50">
                Create Rule
              </button>
            </div>
          </form>
        </div>

        <!-- Empty State -->
        <div v-if="rules.length === 0 && !showForm" class="glass-panel text-center p-16 rounded-2xl border border-white/5 bg-slate-900/40">
          <div class="inline-flex p-4 rounded-full bg-amber-500/10 text-amber-400 mb-4 border border-amber-500/20">
            <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-gray-200">No Alert Rules</h3>
          <p class="text-gray-400 mt-2 max-w-md mx-auto text-sm">
            Set up threshold-based alerts to get notified via WhatsApp when your servers need attention.
          </p>
          <button @click="showForm = true"
            class="mt-6 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-bold glow-primary border border-white/5 transition-all">
            Create First Rule
          </button>
        </div>

        <!-- Rules Table -->
        <div v-if="rules.length > 0" class="glass-panel rounded-2xl border border-white/5 overflow-hidden">
          <div class="divide-y divide-white/5">
            <div v-for="rule in rules" :key="rule.id"
              class="px-6 py-4 flex items-center justify-between hover:bg-white/2 transition-colors"
              :class="{ 'opacity-40': !rule.enabled }"
            >
              <div class="flex items-center gap-4 flex-1">
                <!-- Metric Badge -->
                <span class="text-sm font-bold font-mono w-12" :class="metricColors[rule.metric]">
                  {{ metricLabels[rule.metric] }}
                </span>

                <!-- Condition -->
                <span class="text-gray-300 font-mono text-sm">
                  {{ rule.operator }} {{ rule.threshold }}%
                </span>

                <!-- Server -->
                <span class="text-xs text-gray-500 bg-white/5 px-2 py-0.5 rounded border border-white/5">
                  {{ rule.server ? rule.server.name : 'All Servers' }}
                </span>

                <!-- WhatsApp -->
                <span class="text-xs text-gray-500 font-mono">
                  +{{ rule.whatsapp_number }}
                </span>

                <!-- Cooldown -->
                <span class="text-2xs text-gray-600">
                  {{ rule.cooldown_minutes }}min cooldown
                </span>

                <!-- Last Triggered -->
                <span class="text-2xs" :class="rule.last_triggered_at ? 'text-amber-400' : 'text-gray-600'">
                  {{ formatLastTriggered(rule.last_triggered_at) }}
                </span>
              </div>

              <div class="flex items-center gap-2">
                <!-- Toggle -->
                <button @click="toggleEnabled(rule)"
                  class="px-3 py-1 text-2xs font-mono uppercase tracking-widest rounded-lg border transition-all"
                  :class="rule.enabled
                    ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/20'
                    : 'bg-white/5 text-gray-500 border-white/10 hover:bg-white/10'"
                >
                  {{ rule.enabled ? 'ON' : 'OFF' }}
                </button>

                <!-- Delete -->
                <button @click="deleteRule(rule)"
                  class="p-1.5 bg-white/2 hover:bg-red-500/10 border border-white/5 hover:border-red-500/20 text-gray-500 hover:text-red-400 rounded-lg transition-all"
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
  </AuthenticatedLayout>
</template>
