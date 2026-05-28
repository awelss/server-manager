<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
  status: {
    type: String,
  },
});

const form = useForm({});

const submit = () => {
  form.post(route('verification.send'));
};

const verificationLinkSent = computed(
  () => props.status === 'verification-link-sent',
);
</script>

<template>
  <GuestLayout>
    <Head title="Email Verification" />

    <h2 class="text-xl font-bold text-gray-100 mb-1">Verify your email</h2>
    <p class="text-sm text-gray-400 mb-6">
      Thanks for signing up! Please verify your email address by clicking the link we sent you.
      If you didn't receive it, we'll gladly send another.
    </p>

    <div v-if="verificationLinkSent" class="mb-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 p-3 text-sm font-medium text-emerald-400">
      A new verification link has been sent to your email address.
    </div>

    <form @submit.prevent="submit">
      <div class="flex items-center justify-between gap-4">
        <PrimaryButton
          :class="{ 'opacity-50': form.processing }"
          :disabled="form.processing"
        >
          Resend Email
        </PrimaryButton>

        <Link
          :href="route('logout')"
          method="post"
          as="button"
          class="text-sm text-gray-400 hover:text-gray-200 transition-colors"
        >
          Log Out
        </Link>
      </div>
    </form>
  </GuestLayout>
</template>
