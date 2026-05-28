<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
  canLogin: {
    type: Boolean,
  },
  canRegister: {
    type: Boolean,
  },
  laravelVersion: {
    type: String,
    required: true,
  },
  phpVersion: {
    type: String,
    required: true,
  },
});
</script>

<template>
  <Head title="VPS Control Hub" />

  <div class="min-h-screen bg-[#090d16] text-gray-200 flex flex-col">
    <!-- Subtle gradient background -->
    <div class="fixed inset-0 pointer-events-none">
      <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[600px] bg-indigo-500/5 rounded-full blur-3xl"></div>
      <div class="absolute bottom-0 right-0 w-[600px] h-[400px] bg-cyan-500/5 rounded-full blur-3xl"></div>
    </div>

    <!-- Navigation -->
    <nav class="relative z-10 mx-auto w-full max-w-7xl px-6 py-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <ApplicationLogo class="h-8 w-8 text-indigo-400" />
          <span class="text-lg font-bold tracking-wide text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-cyan-400">
            VPS Control Hub
          </span>
        </div>

        <div v-if="canLogin" class="flex items-center gap-3">
          <Link
            v-if="$page.props.auth.user"
            :href="route('dashboard')"
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-indigo-500"
          >
            Dashboard
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
            </svg>
          </Link>

          <template v-else>
            <Link
              :href="route('login')"
              class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-300 transition-colors hover:text-white"
            >
              Log in
            </Link>
            <Link
              v-if="canRegister"
              :href="route('register')"
              class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-indigo-500"
            >
              Get Started
            </Link>
          </template>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <main class="relative z-10 flex-1 flex items-center">
      <div class="mx-auto max-w-7xl px-6 py-20 text-center">
        <div class="mx-auto max-w-3xl">
          <div class="inline-flex items-center gap-2 rounded-full border border-indigo-500/20 bg-indigo-500/10 px-4 py-1.5 text-xs font-medium text-indigo-400 mb-8">
            <span class="h-1.5 w-1.5 rounded-full bg-indigo-400 animate-pulse"></span>
            Real-Time Agent-Based Monitoring
          </div>

          <h1 class="text-5xl font-extrabold tracking-tight sm:text-6xl lg:text-7xl">
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-cyan-400">
              VPS Control Hub
            </span>
          </h1>

          <p class="mt-6 text-lg text-gray-400 leading-relaxed max-w-2xl mx-auto">
            Monitor your VPS fleet in real-time with lightweight agents, threshold-based alerts, and live log streaming. Professional server management made simple.
          </p>

          <div class="mt-10 flex items-center justify-center gap-4">
            <Link
              v-if="$page.props.auth.user"
              :href="route('dashboard')"
              class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-8 py-3.5 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 glow-primary"
            >
              Go to Dashboard
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
              </svg>
            </Link>
            <template v-else>
              <Link
                v-if="canRegister"
                :href="route('register')"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-8 py-3.5 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 glow-primary"
              >
                Start Monitoring
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
              </Link>
              <Link
                :href="route('login')"
                class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-8 py-3.5 text-sm font-semibold text-gray-300 transition-all hover:bg-white/10 hover:text-white"
              >
                Sign In
              </Link>
            </template>
          </div>
        </div>

        <!-- Feature Cards -->
        <div class="mt-24 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 max-w-5xl mx-auto">
          <!-- Real-time Metrics -->
          <div class="glass-panel rounded-2xl p-6 text-left">
            <div class="inline-flex items-center justify-center h-10 w-10 rounded-lg bg-emerald-500/10 text-emerald-400 mb-4">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
              </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-100 mb-2">Real-Time Metrics</h3>
            <p class="text-sm text-gray-400 leading-relaxed">CPU, RAM, and disk usage monitored via WebSocket push. No polling delays, instant visibility.</p>
          </div>

          <!-- Alert System -->
          <div class="glass-panel rounded-2xl p-6 text-left">
            <div class="inline-flex items-center justify-center h-10 w-10 rounded-lg bg-amber-500/10 text-amber-400 mb-4">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
              </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-100 mb-2">Threshold Alerts</h3>
            <p class="text-sm text-gray-400 leading-relaxed">Set custom thresholds and get WhatsApp notifications when your servers need attention.</p>
          </div>

          <!-- Live Logs -->
          <div class="glass-panel rounded-2xl p-6 text-left">
            <div class="inline-flex items-center justify-center h-10 w-10 rounded-lg bg-blue-500/10 text-blue-400 mb-4">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
              </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-100 mb-2">Live Log Streaming</h3>
            <p class="text-sm text-gray-400 leading-relaxed">Stream server logs in real-time with level filtering. Debug issues as they happen.</p>
          </div>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 border-t border-white/5 py-8 text-center text-xs text-gray-600">
      VPS Control Hub &middot; Powered by Laravel v{{ laravelVersion }}
    </footer>
  </div>
</template>
