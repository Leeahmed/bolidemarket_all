<script setup>
import { toasts, dismiss } from '../stores/toasts'
</script>
<template><div class="toast-stack" aria-live="polite" aria-atomic="false"><div v-for="toast in toasts" :key="toast.id" class="toast"><span>{{ toast.message }}</span><button class="icon-button" aria-label="Fermer la notification" @click="dismiss(toast.id)">×</button></div></div></template>

