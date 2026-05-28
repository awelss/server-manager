<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
  orientation: {
    type: String,
    default: 'horizontal',
    validator: (v) => ['horizontal', 'vertical'].includes(v),
  },
  class: {
    type: [String, Array, Object],
    default: '',
  },
});

const classes = computed(() =>
  cn(
    'shrink-0 bg-white/5',
    props.orientation === 'horizontal' ? 'h-px w-full' : 'h-full w-px',
    props.class
  )
);
</script>

<template>
  <div role="separator" :class="classes" />
</template>
