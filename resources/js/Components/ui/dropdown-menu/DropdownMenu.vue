<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  align: {
    type: String,
    default: 'right',
  },
});

const open = ref(false);
const trigger = ref(null);
const menu = ref(null);

const toggle = () => {
  open.value = !open.value;
};

const close = () => {
  open.value = false;
};

const handleClickOutside = (e) => {
  if (
    menu.value && !menu.value.contains(e.target) &&
    trigger.value && !trigger.value.contains(e.target)
  ) {
    close();
  }
};

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
  <div class="relative">
    <div ref="trigger" @click="toggle">
      <slot name="trigger" />
    </div>
    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <div
        v-if="open"
        ref="menu"
        class="absolute z-50 mt-2 w-48 rounded-lg border border-white/10 bg-slate-900/95 backdrop-blur-xl shadow-xl py-1"
        :class="align === 'right' ? 'right-0' : 'left-0'"
      >
        <slot name="content" :close="close" />
      </div>
    </Transition>
  </div>
</template>
