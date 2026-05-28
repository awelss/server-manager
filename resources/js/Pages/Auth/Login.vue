<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
  canResetPassword: {
    type: Boolean,
  },
  status: {
    type: String,
  },
});

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

const submit = () => {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Log in" />

    <h2 class="text-xl font-bold text-gray-100 mb-1">Welcome back</h2>
    <p class="text-sm text-gray-500 mb-6">Sign in to your account to continue</p>

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

      <div>
        <InputLabel for="password" value="Password" />
        <TextInput
          id="password"
          type="password"
          class="mt-1.5 block w-full"
          v-model="form.password"
          required
          autocomplete="current-password"
          placeholder="Enter your password"
        />
        <InputError class="mt-1.5" :message="form.errors.password" />
      </div>

      <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 cursor-pointer">
          <Checkbox name="remember" v-model:checked="form.remember" />
          <span class="text-sm text-gray-400">Remember me</span>
        </label>

        <Link
          v-if="canResetPassword"
          :href="route('password.request')"
          class="text-sm text-indigo-400 hover:text-indigo-300 transition-colors"
        >
          Forgot password?
        </Link>
      </div>

      <PrimaryButton
        class="w-full justify-center"
        :class="{ 'opacity-50': form.processing }"
        :disabled="form.processing"
      >
        Sign In
      </PrimaryButton>

      <p class="text-center text-sm text-gray-500">
        Contact your administrator to get an account.
      </p>
    </form>
  </GuestLayout>
</template>
