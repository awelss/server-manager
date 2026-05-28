<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  href: {
    type: String,
    required: true,
  },
  active: {
    type: Boolean,
  },
});

const classes = computed(() =>
  props.active
    ? 'flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-indigo-400 bg-indigo-500/10 border-l-2 border-indigo-400 rounded-r-lg transition-all'
    : 'flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-gray-400 hover:text-gray-200 hover:bg-white/5 border-l-2 border-transparent rounded-r-lg transition-all',
);
</script>

<template>
  <Link :href="href" :class="classes">
    <slot />
  </Link>
</template>
