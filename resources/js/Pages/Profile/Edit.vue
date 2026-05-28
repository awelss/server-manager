<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  mustVerifyEmail: {
    type: Boolean,
  },
  status: {
    type: String,
  },
});
</script>

<template>
  <Head title="Profile" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-2xl font-extrabold tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-cyan-400 font-sans">
        Profile Settings
      </h2>
      <p class="text-xs text-gray-500 mt-0.5">Manage your account information and security</p>
    </template>

    <div class="py-10 bg-slate-950/20 min-h-screen text-gray-200">
      <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="glass-panel rounded-2xl p-6 sm:p-8 border border-white/5">
          <UpdateProfileInformationForm
            :must-verify-email="mustVerifyEmail"
            :status="status"
            class="max-w-xl"
          />
        </div>

        <div class="glass-panel rounded-2xl p-6 sm:p-8 border border-white/5">
          <UpdatePasswordForm class="max-w-xl" />
        </div>

        <div class="glass-panel rounded-2xl p-6 sm:p-8 border border-white/5">
          <DeleteUserForm class="max-w-xl" />
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
