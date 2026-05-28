<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  href: {
    type: String,
    required: true,
  },
});
</script>

<template>
  <Link
    :href="href"
    class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 transition-colors hover:bg-white/5 hover:text-white focus:bg-white/5 focus:outline-none"
  >
    <slot />
  </Link>
</template>
