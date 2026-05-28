<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-[#090d16] px-4">
    <!-- Subtle background glow -->
    <div class="fixed inset-0 pointer-events-none">
      <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[600px] h-[400px] bg-indigo-500/5 rounded-full blur-3xl"></div>
    </div>

    <!-- Logo -->
    <div class="relative z-10 mb-8">
      <Link href="/" class="flex items-center gap-3">
        <ApplicationLogo class="h-10 w-10 text-indigo-400" />
        <span class="text-xl font-bold tracking-wide text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-cyan-400">
          VPS Control Hub
        </span>
      </Link>
    </div>

    <!-- Card -->
    <div class="relative z-10 w-full max-w-md glass-panel rounded-2xl border border-white/10 px-8 py-8 shadow-2xl">
      <slot />
    </div>
  </div>
</template>
