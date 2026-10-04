<script setup>
import { onMounted, computed, ref } from 'vue'
import { favorites, loadFavorites } from '../stores/favorites'
import VehicleCard from '../components/VehicleCard.vue'
import FormError from '../components/FormError.vue'
import PaginationNav from '../components/PaginationNav.vue'
const page = ref(1)
const last = computed(() => Math.max(1, Math.ceil(favorites.items.length / 12)))
const current = computed(() => Math.min(page.value, last.value))
const items = computed(() => favorites.items.slice((current.value - 1) * 12, current.value * 12))
function load() { loadFavorites(true).catch(() => {}) }
onMounted(load)
</script>
<template><p class="eyebrow">VOTRE SÉLECTION</p><h1>Mes favoris</h1><p class="page-lead">Les véhicules que vous souhaitez retrouver.</p><p v-if="favorites.loading" role="status">Chargement de vos favoris…</p><template v-else-if="favorites.error"><FormError :error="favorites.error" /><button class="button" @click="load">Réessayer</button></template><div v-else-if="!favorites.items.length" class="empty-state"><h2>Vous n’avez encore aucun véhicule favori.</h2><RouterLink class="button" to="/vehicles">Explorer le marché</RouterLink></div><template v-else><div class="vehicle-grid account-favorites"><VehicleCard v-for="vehicle in items" :key="vehicle.id" :vehicle="vehicle" /></div><PaginationNav :meta="{ current_page: current, last_page: last }" @change="page = $event" /></template></template>

