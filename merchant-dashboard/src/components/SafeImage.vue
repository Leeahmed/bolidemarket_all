<script setup>
import { ref, watch } from 'vue'
import VehicleSilhouette from './VehicleSilhouette.vue'
const props = defineProps({ src: String, alt: { type: String, default: '' }, eager: Boolean })
const broken = ref(false)
watch(() => props.src, () => { broken.value = false })
</script>
<template><img v-if="src && !broken" :src="src" :alt="alt" :loading="eager ? 'eager' : 'lazy'" @error="broken = true" /><div v-else class="image-placeholder" role="img" :aria-label="alt || 'Photo non disponible'"><VehicleSilhouette /><img class="placeholder-logo" src="/images/logo-horizontal.webp" alt="" /><span>Photographies à venir</span></div></template>
