<script setup>
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps({
  class: {
    type: [String, Array, Object],
    default: '',
  },
  modelValue: [String, Number],
});

const emit = defineEmits(['update:modelValue']);

const classes = computed(() =>
  cn(
    'flex h-10 w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-2 text-sm text-gray-200 placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50 disabled:cursor-not-allowed disabled:opacity-50 transition-all',
    props.class
  )
);
</script>

<template>
  <input
    :class="classes"
    :value="modelValue"
    @input="emit('update:modelValue', $event.target.value)"
  />
</template>
