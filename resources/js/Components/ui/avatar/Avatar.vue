<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
  name: String,
  size: {
    type: String,
    default: 'default',
    validator: (v) => ['sm', 'default', 'lg'].includes(v),
  },
  class: {
    type: [String, Array, Object],
    default: '',
  },
});

const sizeClasses = {
  sm: 'h-7 w-7 text-xs',
  default: 'h-9 w-9 text-sm',
  lg: 'h-11 w-11 text-base',
};

const initials = computed(() => {
  if (!props.name) return '?';
  return props.name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
});

const classes = computed(() =>
  cn(
    'inline-flex items-center justify-center rounded-full bg-indigo-600/20 text-indigo-400 font-semibold shrink-0',
    sizeClasses[props.size],
    props.class
  )
);
</script>

<template>
  <div :class="classes">
    {{ initials }}
  </div>
</template>
