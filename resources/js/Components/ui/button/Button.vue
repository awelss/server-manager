<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'destructive', 'outline', 'secondary', 'ghost', 'link'].includes(v),
  },
  size: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'sm', 'lg', 'icon'].includes(v),
  },
  as: {
    type: [String, Object],
    default: 'button',
  },
  class: {
    type: [String, Array, Object],
    default: '',
  },
  disabled: Boolean,
});

const variantClasses = {
  default: 'bg-indigo-600 text-white hover:bg-indigo-500 shadow-sm',
  destructive: 'bg-red-600 text-white hover:bg-red-500 shadow-sm',
  outline: 'border border-white/10 bg-transparent text-gray-300 hover:bg-white/5 hover:text-white',
  secondary: 'bg-white/5 text-gray-300 hover:bg-white/10 border border-white/5',
  ghost: 'text-gray-400 hover:bg-white/5 hover:text-white',
  link: 'text-indigo-400 underline-offset-4 hover:underline',
};

const sizeClasses = {
  default: 'h-10 px-4 py-2 text-sm',
  sm: 'h-9 px-3 text-xs',
  lg: 'h-11 px-8 text-base',
  icon: 'h-10 w-10',
};

const classes = computed(() =>
  cn(
    'inline-flex items-center justify-center whitespace-nowrap rounded-lg font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/50 disabled:pointer-events-none disabled:opacity-50 cursor-pointer',
    variantClasses[props.variant],
    sizeClasses[props.size],
    props.class
  )
);
</script>

<template>
  <component :is="as" :class="classes" :disabled="disabled">
    <slot />
  </component>
</template>
