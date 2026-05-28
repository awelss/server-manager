<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
  class: {
    type: [String, Array, Object],
    default: '',
  },
  for: String,
});

const classes = computed(() =>
  cn(
    'text-sm font-medium text-gray-300 leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
    props.class
  )
);
</script>

<template>
  <label :for="$props.for" :class="classes">
    <slot />
  </label>
</template>
