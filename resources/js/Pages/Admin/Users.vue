<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  users: Array,
  allServers: Array,
});

// Create User Modal
const showCreateModal = ref(false);
const createForm = useForm({
  name: '',
  email: '',
  password: '',
  role: 'user',
});

const submitCreate = () => {
  createForm.post(route('admin.users.store'), {
    preserveScroll: true,
    onSuccess: () => {
      createForm.reset();
      showCreateModal.value = false;
    },
  });
};

// Assign Servers Modal
const showAssignModal = ref(false);
const assigningUser = ref(null);
const selectedServerIds = ref([]);

const openAssignModal = (user) => {
  assigningUser.value = user;
  selectedServerIds.value = [...user.assigned_servers];
  showAssignModal.value = true;
};

const toggleServer = (serverId) => {
  const idx = selectedServerIds.value.indexOf(serverId);
  if (idx > -1) {
    selectedServerIds.value.splice(idx, 1);
  } else {
    selectedServerIds.value.push(serverId);
  }
};

const submitAssign = () => {
  router.post(route('admin.users.assignServers', assigningUser.value.id), {
    server_ids: selectedServerIds.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      showAssignModal.value = false;
      assigningUser.value = null;
    },
  });
};

// Toggle Role
const toggleRole = (user) => {
  const newRole = user.role === 'admin' ? 'user' : 'admin';
  if (newRole === 'user' && user.id === usePage().props.auth.user.id) {
    return; // Can't demote yourself
  }
  router.patch(route('admin.users.update', user.id), {
    role: newRole,
  }, { preserveScroll: true });
};

// Delete User
const deleteUser = (user) => {
  if (confirm(`Delete user "${user.name}"? This will also delete all their servers and data.`)) {
    router.delete(route('admin.users.destroy', user.id), {
      preserveScroll: true,
    });
  }
};

import { usePage } from '@inertiajs/vue3';

const formatDate = (dateStr) => {
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};
</script>

<template>
  <Head title="User Management" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex justify-between items-center w-full">
        <div>
          <h2 class="text-2xl font-extrabold tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-cyan-400 font-sans">
            User Management
          </h2>
          <p class="text-xs text-gray-500 mt-0.5">Manage users and server assignments</p>
        </div>
        <button
          @click="showCreateModal = true"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-semibold tracking-wide shadow-lg border border-white/10 glow-primary transition-all flex items-center gap-1.5"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          Create User
        </button>
      </div>
    </template>

    <div class="py-10 bg-slate-950/20 min-h-screen text-gray-200">
      <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

        <!-- Empty State -->
        <div v-if="users.length === 0" class="glass-panel text-center p-16 rounded-2xl border border-white/5 bg-slate-900/40">
          <h3 class="text-xl font-bold text-gray-200">No Users</h3>
          <p class="text-gray-400 mt-2 text-sm">Create your first user to get started.</p>
        </div>

        <!-- Users Table -->
        <div v-else class="glass-panel rounded-2xl border border-white/5 overflow-hidden">
          <div class="divide-y divide-white/5">
            <div
              v-for="user in users"
              :key="user.id"
              class="px-6 py-4 flex items-center justify-between hover:bg-white/2 transition-colors"
            >
              <div class="flex items-center gap-4 flex-1 min-w-0">
                <!-- Avatar -->
                <div class="h-9 w-9 rounded-full bg-indigo-600/20 text-indigo-400 font-semibold text-sm flex items-center justify-center shrink-0">
                  {{ user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) }}
                </div>

                <!-- Name & Email -->
                <div class="min-w-0">
                  <div class="font-medium text-gray-200 truncate">{{ user.name }}</div>
                  <div class="text-xs text-gray-500 truncate">{{ user.email }}</div>
                </div>

                <!-- Role Badge -->
                <span
                  class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider border shrink-0"
                  :class="user.role === 'admin'
                    ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20'
                    : 'bg-white/5 text-gray-400 border-white/10'"
                >
                  {{ user.role }}
                </span>

                <!-- Server Count -->
                <span class="text-xs text-gray-500 shrink-0">
                  {{ user.servers_count }} owned &middot; {{ user.assigned_servers.length }} assigned
                </span>

                <!-- Join Date -->
                <span class="text-xs text-gray-600 shrink-0 hidden lg:block">
                  {{ formatDate(user.created_at) }}
                </span>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-2 shrink-0 ml-4">
                <!-- Assign Servers (only for non-admin users) -->
                <button
                  v-if="user.role === 'user'"
                  @click="openAssignModal(user)"
                  class="px-3 py-1 text-xs font-mono uppercase tracking-widest rounded-lg border bg-white/5 text-gray-400 border-white/10 hover:bg-indigo-500/10 hover:text-indigo-400 hover:border-indigo-500/20 transition-all"
                  title="Assign Servers"
                >
                  Servers
                </button>

                <!-- Toggle Role -->
                <button
                  @click="toggleRole(user)"
                  :disabled="user.id === $page.props.auth.user.id"
                  class="px-3 py-1 text-xs font-mono uppercase tracking-widest rounded-lg border transition-all disabled:opacity-30"
                  :class="user.role === 'admin'
                    ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20 hover:bg-indigo-500/20'
                    : 'bg-white/5 text-gray-500 border-white/10 hover:bg-white/10'"
                >
                  {{ user.role === 'admin' ? 'Admin' : 'User' }}
                </button>

                <!-- Delete -->
                <button
                  @click="deleteUser(user)"
                  :disabled="user.id === $page.props.auth.user.id"
                  class="p-1.5 bg-white/2 hover:bg-red-500/10 border border-white/5 hover:border-red-500/20 text-gray-500 hover:text-red-400 rounded-lg transition-all disabled:opacity-30 disabled:hover:bg-white/2 disabled:hover:text-gray-500"
                  title="Delete User"
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

    <!-- Create User Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showCreateModal = false"></div>
        <div class="relative glass-panel border border-white/10 rounded-2xl shadow-2xl w-full max-w-md bg-slate-900/95 text-gray-200 p-6">
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-indigo-400">Create User</h3>
            <button @click="showCreateModal = false" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <form @submit.prevent="submitCreate" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Name</label>
              <input
                v-model="createForm.name"
                type="text"
                required
                placeholder="Full name"
                class="w-full px-3 py-2 bg-slate-950/80 border border-white/10 rounded-lg text-gray-200 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
              />
              <p v-if="createForm.errors.name" class="text-sm text-red-400 mt-1">{{ createForm.errors.name }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Email</label>
              <input
                v-model="createForm.email"
                type="email"
                required
                placeholder="user@example.com"
                class="w-full px-3 py-2 bg-slate-950/80 border border-white/10 rounded-lg text-gray-200 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
              />
              <p v-if="createForm.errors.email" class="text-sm text-red-400 mt-1">{{ createForm.errors.email }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Password</label>
              <input
                v-model="createForm.password"
                type="password"
                required
                placeholder="Minimum 8 characters"
                class="w-full px-3 py-2 bg-slate-950/80 border border-white/10 rounded-lg text-gray-200 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
              />
              <p v-if="createForm.errors.password" class="text-sm text-red-400 mt-1">{{ createForm.errors.password }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-300 mb-1">Role</label>
              <select
                v-model="createForm.role"
                class="w-full px-3 py-2 bg-slate-950/80 border border-white/10 rounded-lg text-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
              >
                <option value="user">User</option>
                <option value="admin">Admin</option>
              </select>
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <button
                type="button"
                @click="showCreateModal = false"
                class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 text-gray-400 rounded-lg text-sm transition-all"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="createForm.processing"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold glow-primary border border-white/10 transition-all disabled:opacity-50"
              >
                Create User
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Assign Servers Modal -->
    <div v-if="showAssignModal && assigningUser" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="showAssignModal = false"></div>
        <div class="relative glass-panel border border-white/10 rounded-2xl shadow-2xl w-full max-w-md bg-slate-900/95 text-gray-200 p-6">
          <div class="flex justify-between items-center mb-4">
            <div>
              <h3 class="text-lg font-bold text-indigo-400">Assign Servers</h3>
              <p class="text-xs text-gray-500 mt-0.5">{{ assigningUser.name }} ({{ assigningUser.email }})</p>
            </div>
            <button @click="showAssignModal = false" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div v-if="allServers.length === 0" class="text-center py-8 text-sm text-gray-500">
            No servers available to assign.
          </div>

          <div v-else class="space-y-2 max-h-80 overflow-y-auto pr-1">
            <label
              v-for="server in allServers"
              :key="server.id"
              class="flex items-center gap-3 px-3 py-2.5 rounded-lg border cursor-pointer transition-all"
              :class="selectedServerIds.includes(server.id)
                ? 'bg-indigo-500/10 border-indigo-500/20 text-indigo-300'
                : 'bg-white/2 border-white/5 text-gray-400 hover:bg-white/5'"
            >
              <input
                type="checkbox"
                :checked="selectedServerIds.includes(server.id)"
                @change="toggleServer(server.id)"
                class="h-4 w-4 rounded border-white/20 bg-slate-950/80 text-indigo-500 focus:ring-2 focus:ring-indigo-500/50 focus:ring-offset-0"
              />
              <div class="min-w-0">
                <div class="text-sm font-medium truncate">{{ server.name }}</div>
                <div class="text-xs text-gray-500 font-mono">{{ server.ip_address }}</div>
              </div>
            </label>
          </div>

          <div class="flex justify-between items-center mt-4 pt-4 border-t border-white/5">
            <span class="text-xs text-gray-500">{{ selectedServerIds.length }} server(s) selected</span>
            <div class="flex gap-3">
              <button
                @click="showAssignModal = false"
                class="px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 text-gray-400 rounded-lg text-sm transition-all"
              >
                Cancel
              </button>
              <button
                @click="submitAssign"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold glow-primary border border-white/10 transition-all"
              >
                Save Assignments
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
