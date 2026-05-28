<script setup>
import { watch } from 'vue';

const props = defineProps({
  open: Boolean,
  side: {
    type: String,
    default: 'left',
  },
});

const emit = defineEmits(['update:open']);

const close = () => emit('update:open', false);

watch(() => props.open, (val) => {
  if (val) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});
</script>

<template>
  <Teleport to="body">
    <Transition name="sheet-backdrop">
      <div v-if="open" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm" @click="close" />
    </Transition>
    <Transition :name="side === 'left' ? 'sheet-left' : 'sheet-right'">
      <div
        v-if="open"
        class="fixed inset-y-0 z-50 w-72 bg-slate-950 border-r border-white/5 shadow-2xl"
        :class="side === 'left' ? 'left-0' : 'right-0'"
      >
        <slot />
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.sheet-backdrop-enter-active,
.sheet-backdrop-leave-active {
  transition: opacity 0.2s ease;
}
.sheet-backdrop-enter-from,
.sheet-backdrop-leave-to {
  opacity: 0;
}

.sheet-left-enter-active,
.sheet-left-leave-active {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.sheet-left-enter-from,
.sheet-left-leave-to {
  transform: translateX(-100%);
}

.sheet-right-enter-active,
.sheet-right-leave-active {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.sheet-right-enter-from,
.sheet-right-leave-to {
  transform: translateX(100%);
}
</style>
