<script setup>
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth } from '../stores/auth'
import { favorites, isFavorite, toggleFavorite, loadFavorites } from '../stores/favorites'
import { notify } from '../stores/toasts'
import AppIcon from './AppIcon.vue'
const props = defineProps({ vehicle: { type: Object, required: true }, compact: Boolean })
const route = useRoute(), router = useRouter()
const selected = computed(() => isFavorite(props.vehicle.id))
watch(() => auth.user?.id, id => { if (id) loadFavorites().catch(() => {}) }, { immediate: true })
async function toggle() {
  if (!auth.user) return router.push({ path: '/login', query: { redirect: route.fullPath } })
  try { const added = await toggleFavorite(props.vehicle); if (added !== undefined) notify(added ? 'Ajouté aux favoris.' : 'Retiré des favoris.') }
  catch (error) { notify(error.message) }
}
</script>
<template><button :class="[compact ? 'icon-button' : 'button secondary full', 'favorite-button', { selected }]" :aria-pressed="selected" :aria-label="selected ? 'Retirer des favoris' : 'Ajouter aux favoris'" :disabled="favorites.busy[String(vehicle.id)]" @click="toggle"><AppIcon name="heart" /><span v-if="!compact">{{ selected ? 'Retirer des favoris' : 'Ajouter aux favoris' }}</span></button></template>

