<script setup>
defineProps({ state: String, label: String })
defineEmits(['retry'])
</script>
<template>
  <div v-if="state === 'loading'" class="cards-grid" role="status" :aria-label="`Chargement ${label}`"><div v-for="n in 4" :key="n" class="skeleton-card" aria-hidden="true"><div class="skeleton-photo" /><div class="skeleton-line" /><div class="skeleton-line short" /><div class="skeleton-line" /></div><span class="sr-only">Chargement en cours…</span></div>
  <div v-else-if="state === 'error'" class="feedback" role="alert"><h3>Le catalogue prend une pause.</h3><p>Impossible de charger {{ label }}. Réessayez dans un instant.</p><button class="button button-dark" @click="$emit('retry')">Réessayer</button></div>
  <div v-else-if="state === 'empty'" class="feedback" role="status"><h3>Aucune offre pour le moment.</h3><p>Essayez une autre localisation pour découvrir {{ label }}.</p><a class="button button-dark" href="#recherche">Changer de localisation</a></div>
  <slot v-else />
</template>
