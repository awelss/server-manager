<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
  rules: { type: Array, required: true },
  servers: { type: Array, required: true },
});

const showForm = ref(false);
const metricOptions = [
  ['cpu', 'CPU Usage', '%'],
  ['ram', 'RAM Usage', '%'],
  ['disk', 'Disk Usage', '%'],
  ['steal', 'CPU Steal', '%'],
  ['iowait', 'I/O Wait', '%'],
  ['swap', 'Swap Usage', '%'],
  ['inode', 'Inode Usage', '%'],
  ['zombie', 'Zombie Processes', 'count'],
  ['processes', 'Process Count', 'count'],
  ['load1', 'Load Average (1m)', 'load'],
];

const form = useForm({
  server_id: '',
  metric: 'cpu',
  operator: '>',
  threshold: 85,
  for_minutes: 5,
  cooldown_minutes: 30,
  recovery_enabled: true,
  whatsapp_number: '',
});

const selectedMetric = computed(() => metricOptions.find(x => x[0] === form.metric) || metricOptions[0]);

function submit() {
  form.post(route('alerts.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      form.metric = 'cpu';
      form.operator = '>';
      form.threshold = 85;
      form.for_minutes = 5;
      form.cooldown_minutes = 30;
      form.recovery_enabled = true;
      showForm.value = false;
    },
  });
}

function toggleEnabled(rule) {
  router.patch(route('alerts.update', rule.id), { enabled: !rule.enabled }, { preserveScroll: true });
}

function deleteRule(rule) {
  if (confirm('Delete this alert rule?')) {
    router.delete(route('alerts.destroy', rule.id), { preserveScroll: true });
  }
}

function metricLabel(metric) {
  return metricOptions.find(x => x[0] === metric)?.[1] || metric.toUpperCase();
}

function metricUnit(metric) {
  return metricOptions.find(x => x[0] === metric)?.[2] || '';
}

function valueText(metric, value) {
  const unit = metricUnit(metric);
  if (unit === '%') return `${value}%`;
  if (unit === 'count') return `${value}`;
  return `${value}`;
}

function age(date) {
  if (!date) return 'Never';
  const mins = Math.floor((Date.now() - new Date(date).getTime()) / 60000);
  if (mins < 1) return 'Just now';
  if (mins < 60) return `${mins}m ago`;
  if (mins < 1440) return `${Math.floor(mins / 60)}h ago`;
  return `${Math.floor(mins / 1440)}d ago`;
}
</script>

<template>
  <Head title="Smart Alerts" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between w-full">
        <div>
          <h2 class="text-2xl font-extrabold tracking-wide text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-cyan-400">Smart Alerts</h2>
          <p class="text-xs text-gray-500 mt-0.5">Sustained thresholds, cooldowns and recovery notifications</p>
        </div>
        <div class="flex gap-2">
          <Link :href="route('infrastructure')" class="px-3 py-2 bg-white/5 border border-white/10 text-gray-300 rounded-lg text-xs hover:bg-white/10">Infrastructure</Link>
          <button @click="showForm = !showForm" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold">Add Rule</button>
        </div>
      </div>
    </template>

    <div class="py-8 min-h-screen bg-slate-950/20 text-gray-200">
      <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-5">
        <div v-if="showForm" class="glass-panel rounded-2xl border border-white/5 bg-slate-900/30 p-6">
          <form @submit.prevent="submit" class="space-y-5">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <label class="space-y-1">
                <span class="text-xs text-gray-500">Server</span>
                <select v-model="form.server_id" class="w-full rounded-lg bg-slate-800/80 border-white/10 text-sm text-gray-200">
                  <option value="">All My Servers</option>
                  <option v-for="s in servers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </label>

              <label class="space-y-1">
                <span class="text-xs text-gray-500">Metric</span>
                <select v-model="form.metric" class="w-full rounded-lg bg-slate-800/80 border-white/10 text-sm text-gray-200">
                  <option v-for="m in metricOptions" :key="m[0]" :value="m[0]">{{ m[1] }}</option>
                </select>
              </label>

              <label class="space-y-1">
                <span class="text-xs text-gray-500">WhatsApp</span>
                <input v-model="form.whatsapp_number" required placeholder="6281234567890" class="w-full rounded-lg bg-slate-800/80 border-white/10 text-sm text-gray-200" />
              </label>

              <div class="space-y-1">
                <span class="text-xs text-gray-500">Condition</span>
                <div class="flex gap-2">
                  <select v-model="form.operator" class="w-24 rounded-lg bg-slate-800/80 border-white/10 text-sm text-gray-200">
                    <option value=">">&gt;</option>
                    <option value=">=">&gt;=</option>
                    <option value="<">&lt;</option>
                    <option value="<=">&lt;=</option>
                  </select>
                  <div class="relative flex-1">
                    <input v-model="form.threshold" type="number" min="0" step="0.1" required class="w-full rounded-lg bg-slate-800/80 border-white/10 text-sm text-gray-200" />
                    <span class="absolute right-3 top-2.5 text-xs text-gray-500">{{ selectedMetric[2] === '%' ? '%' : '' }}</span>
                  </div>
                </div>
              </div>

              <label class="space-y-1">
                <span class="text-xs text-gray-500">Must stay breached for</span>
                <div class="relative">
                  <input v-model="form.for_minutes" type="number" min="0" max="1440" required class="w-full rounded-lg bg-slate-800/80 border-white/10 text-sm text-gray-200 pr-14" />
                  <span class="absolute right-3 top-2.5 text-xs text-gray-500">min</span>
                </div>
              </label>

              <label class="space-y-1">
                <span class="text-xs text-gray-500">Repeat cooldown</span>
                <div class="relative">
                  <input v-model="form.cooldown_minutes" type="number" min="1" max="10080" required class="w-full rounded-lg bg-slate-800/80 border-white/10 text-sm text-gray-200 pr-14" />
                  <span class="absolute right-3 top-2.5 text-xs text-gray-500">min</span>
                </div>
              </label>
            </div>

            <label class="flex items-center gap-3 text-sm text-gray-300">
              <input v-model="form.recovery_enabled" type="checkbox" class="rounded border-white/20 bg-slate-800 text-indigo-500" />
              Send WhatsApp recovery message when metric returns to normal
            </label>

            <div class="rounded-xl border border-cyan-500/10 bg-cyan-500/5 p-3 text-xs text-cyan-200/80">
              Example: CPU &gt; 85%, sustained 5 min, cooldown 30 min. A short spike will not alert; a sustained breach will alert once, then repeat at most every 30 min until recovery.
            </div>

            <div class="flex justify-end gap-2">
              <button type="button" @click="showForm = false" class="px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-sm text-gray-400">Cancel</button>
              <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-sm text-white font-semibold disabled:opacity-50">Create Rule</button>
            </div>
          </form>
        </div>

        <div v-if="rules.length === 0 && !showForm" class="glass-panel rounded-2xl border border-white/5 bg-slate-900/30 p-12 text-center">
          <div class="text-lg font-bold">No alert rules</div>
          <div class="text-sm text-gray-500 mt-2">Create sustained alerts for CPU, RAM, disk, steal, iowait, zombies and more.</div>
        </div>

        <div v-for="rule in rules" :key="rule.id" class="glass-panel rounded-xl border border-white/5 bg-slate-900/30 p-4" :class="{ 'opacity-50': !rule.enabled }">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-bold text-gray-100">{{ metricLabel(rule.metric) }}</span>
              <span class="font-mono text-cyan-300">{{ rule.operator }} {{ valueText(rule.metric, rule.threshold) }}</span>
              <span class="text-2xs px-2 py-0.5 rounded border border-white/10 bg-white/5 text-gray-400">{{ rule.server?.name || 'All Servers' }}</span>
              <span class="text-2xs px-2 py-0.5 rounded border" :class="rule.is_active ? 'border-red-500/20 bg-red-500/10 text-red-400' : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-400'">{{ rule.is_active ? 'ACTIVE BREACH' : 'NORMAL' }}</span>
            </div>

            <div class="flex items-center gap-2">
              <button @click="toggleEnabled(rule)" class="px-3 py-1 rounded-lg border text-2xs uppercase font-bold" :class="rule.enabled ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-400' : 'border-white/10 bg-white/5 text-gray-500'">{{ rule.enabled ? 'ON' : 'OFF' }}</button>
              <button @click="deleteRule(rule)" class="px-3 py-1 rounded-lg border border-red-500/20 bg-red-500/5 text-2xs text-red-400">Delete</button>
            </div>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mt-4 text-xs">
            <div><span class="block text-gray-600">Sustain</span><span class="text-gray-300">{{ rule.for_minutes }} min</span></div>
            <div><span class="block text-gray-600">Cooldown</span><span class="text-gray-300">{{ rule.cooldown_minutes }} min</span></div>
            <div><span class="block text-gray-600">Recovery</span><span class="text-gray-300">{{ rule.recovery_enabled ? 'On' : 'Off' }}</span></div>
            <div><span class="block text-gray-600">Last alert</span><span class="text-gray-300">{{ age(rule.last_triggered_at) }}</span></div>
            <div><span class="block text-gray-600">Last value</span><span class="text-gray-300">{{ rule.last_value == null ? '—' : valueText(rule.metric, rule.last_value) }}</span></div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
