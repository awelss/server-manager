<script setup>
import { ref, watch, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  apiBaseUrl: {
    type: String,
    required: true
  }
});

const emit = defineEmits(['close']);

const form = useForm({
  name: '',
  ip_address: '',
});

const isSuccessState = ref(false);
const newServerDetails = ref(null);
const copySuccess = ref(false);

const page = usePage();

// Monitor session flash messages for newly created servers
watch(
  () => page.props.flash,
  (newFlash) => {
    if (newFlash && newFlash.new_server) {
      newServerDetails.value = newFlash.new_server;
      isSuccessState.value = true;
      form.reset();
    }
  },
  { deep: true }
);

const installCommand = computed(() => {
  if (!newServerDetails.value) return '';
  return `curl -sSL ${props.apiBaseUrl}/agent/install.sh | sudo bash -s -- ${props.apiBaseUrl} ${newServerDetails.value.agent_token}`;
});

const copyToClipboard = async () => {
  try {
    await navigator.clipboard.writeText(installCommand.value);
    copySuccess.value = true;
    setTimeout(() => {
      copySuccess.value = false;
    }, 2000);
  } catch (err) {
    console.error('Failed to copy text: ', err);
  }
};

const submit = () => {
  form.post(route('servers.store'), {
    onSuccess: () => {
      // Handled by flash watcher
    },
  });
};

const handleClose = () => {
  isSuccessState.value = false;
  newServerDetails.value = null;
  form.reset();
  emit('close');
};
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 transition-opacity bg-black/60 backdrop-blur-sm" aria-hidden="true" @click="handleClose"></div>

      <!-- Centering trick -->
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <!-- Modal Content Panel -->
      <div class="inline-block align-bottom glass-panel border border-white/10 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full bg-slate-900/90 text-gray-200">
        
        <!-- Form State -->
        <form v-if="!isSuccessState" @submit.prevent="submit" class="p-6">
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-indigo-400 font-sans tracking-wide">Add New VPS Instance</h3>
            <button type="button" @click="handleClose" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="space-y-4">
            <div>
              <label for="name" class="block text-sm font-semibold text-gray-300 mb-1">Server Name</label>
              <input 
                v-model="form.name"
                type="text" 
                id="name" 
                required
                placeholder="e.g., Production-SG"
                class="w-full px-4 py-2.5 bg-slate-950/80 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 text-gray-200 placeholder-gray-500 transition-all"
              />
              <span v-if="form.errors.name" class="text-sm text-red-400 mt-1 block">{{ form.errors.name }}</span>
            </div>

            <div>
              <label for="ip" class="block text-sm font-semibold text-gray-300 mb-1">IP Address</label>
              <input 
                v-model="form.ip_address"
                type="text" 
                id="ip" 
                required
                placeholder="e.g., 198.51.100.12"
                class="w-full px-4 py-2.5 bg-slate-950/80 border border-white/10 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 text-gray-200 placeholder-gray-500 transition-all"
              />
              <span v-if="form.errors.ip_address" class="text-sm text-red-400 mt-1 block">{{ form.errors.ip_address }}</span>
            </div>
          </div>

          <div class="mt-8 flex justify-end gap-3">
            <button 
              type="button" 
              @click="handleClose"
              class="px-5 py-2.5 bg-white/5 hover:bg-white/10 border border-white/5 rounded-xl text-sm font-medium text-gray-300 hover:text-white transition-all"
            >
              Cancel
            </button>
            <button 
              type="submit"
              :disabled="form.processing"
              class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 glow-primary rounded-xl text-sm font-medium text-white transition-all flex items-center justify-center"
            >
              <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Generate Agent Command
            </button>
          </div>
        </form>

        <!-- Success & Installer Script State -->
        <div v-else class="p-6">
          <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-emerald-500/10 text-emerald-400 mb-3 border border-emerald-500/20 glow-success">
              <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <h3 class="text-2xl font-bold text-emerald-400">Server Registered!</h3>
            <p class="text-gray-400 text-sm mt-1">Ready to install the monitoring agent on <span class="text-indigo-300 font-semibold">{{ newServerDetails?.name }}</span></p>
          </div>

          <div class="space-y-4">
            <p class="text-gray-300 text-sm leading-relaxed">
              Login to your target VPS via SSH and run the following command. The installer will automatically download the agent, configure the token, and register it as a background service.
            </p>

            <!-- Command Box -->
            <div class="relative console-bg rounded-xl p-4 border border-white/5 shadow-inner">
              <div class="text-xs text-gray-500 mb-2 font-mono flex justify-between">
                <span>VPS SHELL</span>
                <span>BASH COMMAND</span>
              </div>
              <pre class="text-xs text-indigo-300 whitespace-pre-wrap break-all font-mono select-all pr-12 leading-relaxed">{{ installCommand }}</pre>
              
              <button 
                type="button" 
                @click="copyToClipboard" 
                class="absolute top-12 right-4 p-2 bg-white/5 hover:bg-indigo-600 border border-white/10 rounded-lg text-gray-400 hover:text-white transition-all"
                title="Copy to clipboard"
              >
                <svg v-if="!copySuccess" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                </svg>
                <svg v-else class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
              </button>
            </div>
            
            <div v-if="copySuccess" class="text-xs text-emerald-400 text-right font-semibold animate-pulse">
              Command copied to clipboard successfully!
            </div>

            <!-- Notes -->
            <div class="p-3 bg-amber-500/5 border border-amber-500/10 rounded-xl flex gap-3 text-xs text-amber-300">
              <svg class="w-5 h-5 flex-shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <div>
                <span class="font-bold">Important:</span> Make sure to run this command as <span class="font-bold">root</span> or with <span class="font-bold">sudo</span> so the installer can configure system services correctly.
              </div>
            </div>
          </div>

          <div class="mt-8 flex justify-center">
            <button 
              type="button" 
              @click="handleClose"
              class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 glow-primary rounded-xl text-sm font-semibold text-white transition-all"
            >
              Done & Go to Dashboard
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
