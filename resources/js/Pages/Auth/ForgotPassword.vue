<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
  status: {
    type: String,
  },
});

const form = useForm({
  email: '',
});

const submit = () => {
  form.post(route('password.email'));
};
</script>

<template>
  <GuestLayout>
    <Head title="Forgot Password" />

    <h2 class="text-xl font-bold text-gray-100 mb-1">Forgot password?</h2>
    <p class="text-sm text-gray-400 mb-6">
      No problem. Enter your email and we'll send you a reset link.
    </p>

    <div v-if="status" class="mb-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 p-3 text-sm font-medium text-emerald-400">
      {{ status }}
    </div>

    <form @submit.prevent="submit" class="space-y-5">
      <div>
        <InputLabel for="email" value="Email" />
        <TextInput
          id="email"
          type="email"
          class="mt-1.5 block w-full"
          v-model="form.email"
          required
          autofocus
          autocomplete="username"
          placeholder="you@example.com"
        />
        <InputError class="mt-1.5" :message="form.errors.email" />
      </div>

      <PrimaryButton
        class="w-full justify-center"
        :class="{ 'opacity-50': form.processing }"
        :disabled="form.processing"
      >
        Send Reset Link
      </PrimaryButton>
    </form>
  </GuestLayout>
</template>
