<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Register" />

    <h2 class="text-xl font-bold text-gray-100 mb-1">Create an account</h2>
    <p class="text-sm text-gray-500 mb-6">Start monitoring your servers in minutes</p>

    <form @submit.prevent="submit" class="space-y-5">
      <div>
        <InputLabel for="name" value="Name" />
        <TextInput
          id="name"
          type="text"
          class="mt-1.5 block w-full"
          v-model="form.name"
          required
          autofocus
          autocomplete="name"
          placeholder="Your full name"
        />
        <InputError class="mt-1.5" :message="form.errors.name" />
      </div>

      <div>
        <InputLabel for="email" value="Email" />
        <TextInput
          id="email"
          type="email"
          class="mt-1.5 block w-full"
          v-model="form.email"
          required
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
          autocomplete="new-password"
          placeholder="Create a password"
        />
        <InputError class="mt-1.5" :message="form.errors.password" />
      </div>

      <div>
        <InputLabel for="password_confirmation" value="Confirm Password" />
        <TextInput
          id="password_confirmation"
          type="password"
          class="mt-1.5 block w-full"
          v-model="form.password_confirmation"
          required
          autocomplete="new-password"
          placeholder="Confirm your password"
        />
        <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
      </div>

      <PrimaryButton
        class="w-full justify-center"
        :class="{ 'opacity-50': form.processing }"
        :disabled="form.processing"
      >
        Create Account
      </PrimaryButton>

      <p class="text-center text-sm text-gray-500">
        Already have an account?
        <Link :href="route('login')" class="text-indigo-400 hover:text-indigo-300 font-medium transition-colors">
          Sign in
        </Link>
      </p>
    </form>
  </GuestLayout>
</template>
