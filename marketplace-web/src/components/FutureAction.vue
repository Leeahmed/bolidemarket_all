<script setup>
import { ref } from 'vue'
import AppIcon from './AppIcon.vue'
defineProps({ label: { type: String, default: 'Bientôt disponible' }, icon: String, compact: Boolean })
const open = ref(false)
</script>
<template><span class="future-action"><button type="button" :class="compact ? 'icon-button' : 'button secondary'" :aria-label="label" :aria-expanded="open" @click="open = !open"><AppIcon v-if="icon" :name="icon" /><span v-if="!compact">{{ label }}</span></button><span v-if="open" role="status" class="future-note">Bientôt disponible. Aucune transaction ni sauvegarde effectuée.</span></span></template>
