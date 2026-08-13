<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <div
          class="absolute inset-0 bg-black/80 backdrop-blur-sm"
          @click="$emit('close')"
        ></div>
        <div class="relative bg-dark-800 rounded-2xl shadow-2xl w-full max-w-md border border-dark-700 overflow-hidden">
          <div class="flex items-center justify-between px-6 py-4 border-b border-dark-700">
            <h3 class="text-lg font-semibold text-white">{{ title }}</h3>
            <button
              @click="$emit('close')"
              class="p-1 hover:bg-dark-700 rounded-lg transition-colors"
            >
              <svg class="w-5 h-5 text-dark-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="px-6 py-4">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' }
})
defineEmits(['close'])
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
