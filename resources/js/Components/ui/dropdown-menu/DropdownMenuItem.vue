<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
  as: {
    type: [String, Object],
    default: 'button',
  },
  class: {
    type: [String, Array, Object],
    default: '',
  },
  destructive: Boolean,
});

const classes = computed(() =>
  cn(
    'flex w-full items-center gap-2 px-3 py-2 text-sm transition-colors cursor-pointer',
    props.destructive
      ? 'text-red-400 hover:bg-red-500/10'
      : 'text-gray-300 hover:bg-white/5 hover:text-white',
    props.class
  )
);
</script>

<template>
  <component :is="as" :class="classes">
    <slot />
  </component>
</template>
