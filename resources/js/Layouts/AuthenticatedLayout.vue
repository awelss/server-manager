<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Avatar } from '@/Components/ui/avatar';
import { Sheet } from '@/Components/ui/sheet';
import { Link } from '@inertiajs/vue3';

const sidebarOpen = ref(false);
const userMenuOpen = ref(false);

const toggleUserMenu = () => { userMenuOpen.value = !userMenuOpen.value; };
const closeUserMenu = () => { userMenuOpen.value = false; };
</script>

<template>
  <div class="min-h-screen bg-[#090d16]">
    <!-- Desktop Sidebar -->
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-white/5 bg-slate-950 lg:flex">
      <!-- Branding -->
      <div class="flex h-16 items-center gap-3 px-6 border-b border-white/5">
        <Link :href="route('dashboard')" class="flex items-center gap-3">
          <ApplicationLogo class="h-8 w-8 text-indigo-400" />
          <span class="text-lg font-bold tracking-wide text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-cyan-400">
            VPS Hub
          </span>
        </Link>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 space-y-1 px-3 py-6">
        <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
          </svg>
          Dashboard
        </NavLink>

        <NavLink :href="route('alerts.index')" :active="route().current('alerts.*')">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
          </svg>
          Alerts
        </NavLink>

        <!-- Admin Section -->
        <template v-if="$page.props.auth.user.role === 'admin'">
          <div class="my-4 h-px bg-white/5"></div>
          <div class="px-4 mb-2">
            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600">Admin</span>
          </div>
          <NavLink :href="route('admin.users.index')" :active="route().current('admin.users.*')">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
            Users
          </NavLink>
        </template>
      </nav>

      <!-- User Section -->
      <div class="border-t border-white/5 p-4">
        <div class="relative">
          <!-- Trigger -->
          <button
            @click="toggleUserMenu"
            class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-sm text-gray-400 transition-all hover:bg-white/5 hover:text-white"
            :class="{ 'bg-white/5 text-white': userMenuOpen }"
          >
            <Avatar :name="$page.props.auth.user.name" size="sm" />
            <div class="flex-1 text-left min-w-0">
              <div class="truncate font-medium text-gray-200">{{ $page.props.auth.user.name }}</div>
              <div class="truncate text-xs text-gray-500">{{ $page.props.auth.user.email }}</div>
            </div>
            <svg
              class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-200"
              :class="{ 'rotate-180': userMenuOpen }"
              fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
            </svg>
          </button>

          <!-- Upward Dropdown Panel -->
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
          >
            <div v-if="userMenuOpen">
              <!-- Backdrop to close on outside click -->
              <div class="fixed inset-0 z-40" @click="closeUserMenu"></div>
              <!-- Panel -->
              <div
                class="absolute bottom-full left-0 right-0 mb-2 rounded-xl border border-white/8 bg-slate-900 shadow-2xl shadow-black/50 overflow-hidden z-50"
              >
                <Link
                  :href="route('profile.edit')"
                  class="flex items-center gap-2.5 px-3 py-2.5 text-sm text-gray-300 hover:bg-white/5 hover:text-white transition-colors"
                  @click="closeUserMenu"
                >
                  <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                  </svg>
                  Profile
                </Link>
                <div class="h-px bg-white/5 mx-3"></div>
                <Link
                  :href="route('logout')"
                  method="post"
                  as="button"
                  class="flex w-full items-center gap-2.5 px-3 py-2.5 text-sm text-gray-300 hover:bg-red-500/10 hover:text-red-400 transition-colors"
                  @click="closeUserMenu"
                >
                  <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                  </svg>
                  Log Out
                </Link>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </aside>

    <!-- Mobile Header -->
    <div class="sticky top-0 z-30 flex h-14 items-center gap-4 border-b border-white/5 bg-slate-950/90 backdrop-blur-xl px-4 lg:hidden">
      <button @click="sidebarOpen = true" class="text-gray-400 hover:text-white transition-colors">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
      </button>
      <Link :href="route('dashboard')" class="flex items-center gap-2">
        <ApplicationLogo class="h-7 w-7 text-indigo-400" />
        <span class="text-base font-bold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-cyan-400">VPS Hub</span>
      </Link>
    </div>

    <!-- Mobile Sidebar Sheet -->
    <Sheet :open="sidebarOpen" @update:open="sidebarOpen = $event" side="left">
      <div class="flex h-full flex-col">
        <!-- Mobile Branding -->
        <div class="flex h-14 items-center justify-between px-4 border-b border-white/5">
          <Link :href="route('dashboard')" class="flex items-center gap-2" @click="sidebarOpen = false">
            <ApplicationLogo class="h-7 w-7 text-indigo-400" />
            <span class="text-base font-bold text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 to-cyan-400">VPS Hub</span>
          </Link>
          <button @click="sidebarOpen = false" class="text-gray-400 hover:text-white transition-colors">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Mobile Nav -->
        <nav class="flex-1 space-y-1 px-3 py-4">
          <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')" @click="sidebarOpen = false">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
            Dashboard
          </ResponsiveNavLink>

          <ResponsiveNavLink :href="route('alerts.index')" :active="route().current('alerts.*')" @click="sidebarOpen = false">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
            Alerts
          </ResponsiveNavLink>
        </nav>

        <!-- Mobile User Section -->
        <div class="border-t border-white/5 p-4">
          <div class="flex items-center gap-3 mb-3 px-2">
            <Avatar :name="$page.props.auth.user.name" size="sm" />
            <div class="min-w-0">
              <div class="truncate text-sm font-medium text-gray-200">{{ $page.props.auth.user.name }}</div>
              <div class="truncate text-xs text-gray-500">{{ $page.props.auth.user.email }}</div>
            </div>
          </div>
          <div class="space-y-1">
            <ResponsiveNavLink :href="route('profile.edit')" @click="sidebarOpen = false">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
              </svg>
              Profile
            </ResponsiveNavLink>
            <Link
              :href="route('logout')"
              method="post"
              as="button"
              class="flex items-center gap-3 w-full px-4 py-2.5 text-sm font-medium text-gray-400 hover:text-gray-200 hover:bg-white/5 border-l-2 border-transparent rounded-r-lg transition-all"
              @click="sidebarOpen = false"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
              </svg>
              Log Out
            </Link>
          </div>
        </div>
      </div>
    </Sheet>

    <!-- Main Content -->
    <div class="lg:pl-64">
      <!-- Page Heading -->
      <header v-if="$slots.header" class="border-b border-white/5 bg-slate-950/50 backdrop-blur-sm">
        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
          <slot name="header" />
        </div>
      </header>

      <!-- Page Content -->
      <main>
        <slot />
      </main>
    </div>
  </div>
</template>
