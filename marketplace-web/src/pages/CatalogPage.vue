<script setup>
import { useVehicleRealtime } from '../composables/useVehicleRealtime'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useResource } from '../composables/useResource'
import { useReferences } from '../composables/useReferences'
import { vehicleService } from '../services/vehicleService'
import { activeCount, cleanQuery, locationText } from '../utils/filters'
import VehicleCard from '../components/VehicleCard.vue'
import FilterPanel from '../components/FilterPanel.vue'
import ResultState from '../components/ResultState.vue'
import PaginationNav from '../components/PaginationNav.vue'
import AppIcon from '../components/AppIcon.vue'
const route = useRoute(), router = useRouter(), { references } = useReferences()
const { data, state, error, load } = useResource()
const query = computed(() => cleanQuery(route.query)), term = ref(''), drawer = ref(null), notice = ref('')
const { newListings, refresh } = useVehicleRealtime(data, reload, { needsReconcile: fields => fields.some(field => ({ inventory_status: ['status'], sale_price_minor: ['min_price','max_price'], rent_daily_minor: ['min_price','max_price'], title: ['q'], description: ['q'], year: ['year_min','year_max'], category_id: ['category'], is_for_sale: ['listing_type'], is_for_rent: ['listing_type'], shop: ['q','country_code','city_id','district_id','radius'] }[field] || []).some(key => query.value[key])) })
let timer
const origin = computed(() => Object.fromEntries(['latitude', 'longitude'].filter(key => query.value[key] !== undefined).map(key => [key, query.value[key]])))
const title = computed(() => { const type = query.value.listing_type === 'sale' ? 'à vendre' : query.value.listing_type === 'rental' ? 'à louer' : 'à découvrir'; const place = locationText(query.value, references); return `${query.value.brand || 'Véhicules'} ${type}${place === 'Toutes les localisations' ? '' : place === 'Votre position GPS' ? ' près de vous' : `${query.value.location_mode === 'rank' ? ' près de ' : ' à '}${place}`}` })
watch(title, value => { document.title = `${value} | BolideMarket` }, { immediate: true })
function reload() { load(signal => vehicleService.list({ ...query.value, per_page: 12 }, signal)) }
watch(() => route.query, () => { clearTimeout(timer); term.value = query.value.q || ''; notice.value = ''; reload() }, { immediate: true })
function navigate(params) { router.push({ path: '/vehicles', query: cleanQuery(params) }) }
function search() { clearTimeout(timer); timer = setTimeout(() => navigate({ ...query.value, q: term.value, page: '' }), 400) }
function submit() { clearTimeout(timer); navigate({ ...query.value, q: term.value, page: '' }) }
function apply(params) { navigate(params); drawer.value?.close() }
function reset() { apply({}) }
function sort(event) {
  const value = event.target.value
  if (value === 'distance' && (query.value.latitude === undefined || query.value.longitude === undefined)) { notice.value = 'Utilisez « Ma localisation » puis « Utiliser ma position » pour trier par distance.'; event.target.value = query.value.sort || ''; return }
  if (value.startsWith('price') && (!query.value.listing_type || !query.value.currency)) { notice.value = 'Choisissez une offre et une devise dans les filtres avant de comparer les prix.'; event.target.value = query.value.sort || ''; return }
  navigate({ ...query.value, sort: value, page: '' })
}
onBeforeUnmount(() => clearTimeout(timer))
</script>
<template><section class="catalog-intro"><div class="container"><p class="eyebrow">LE BON VÉHICULE. AU BON ENDROIT.</p><h1>{{ title }}</h1><p>Explorez les offres des professionnels, à votre rythme.</p><form class="main-search" role="search" @submit.prevent="submit"><AppIcon name="search" /><input v-model="term" aria-label="Rechercher dans le catalogue" placeholder="Marque, modèle, ville ou professionnel..." maxlength="120" @input="search" /><button class="button" type="submit">Rechercher <AppIcon /></button></form></div></section><div class="container catalog-layout"><aside class="desktop-filters"><FilterPanel :query="query" @apply="apply" @reset="reset" /></aside><section class="results" aria-label="Résultats de recherche" :aria-busy="state === 'loading'"><div class="results-toolbar"><div><p class="result-count" aria-live="polite">{{ state === 'success' ? `${data?.meta?.total || 0} véhicules` : 'Votre sélection' }}</p><small>{{ activeCount(query) }} filtre(s) actif(s)</small></div><button class="button secondary mobile-filter-button" @click="drawer.showModal()">Filtres ({{ activeCount(query) }})</button><label class="sort-label">Trier par<select :value="query.sort || ''" @change="sort"><option value="">Pertinence / localisation</option><option value="distance">Plus proches</option><option value="newest">Plus récents</option><option value="price_asc">Prix croissant</option><option value="price_desc">Prix décroissant</option><option value="year_desc">Année récente</option><option value="mileage_asc">Kilométrage</option></select></label></div><p v-if="newListings" role="status" class="notice">De nouvelles annonces sont disponibles. <button class="text-button" @click="refresh">Actualiser</button></p><p v-if="notice" role="status" class="notice">{{ notice }}</p><p v-if="query.latitude !== undefined" class="help">Distances à vol d’oiseau depuis votre position GPS, calculées par le catalogue.</p><ResultState :state="state" :error="error" :empty="!data?.data?.length" @retry="reload" @reset="reset"><div class="vehicle-grid"><VehicleCard v-for="vehicle in data.data" :key="vehicle.id" :vehicle="vehicle" :intention="query.listing_type" :origin="origin" /></div><PaginationNav :meta="data.meta" @change="navigate({ ...query, page: $event })" /></ResultState></section></div><dialog ref="drawer" class="filter-dialog"><div class="dialog-heading"><h2>Vos filtres</h2><button class="icon-button" aria-label="Fermer les filtres" @click="drawer.close()"><AppIcon name="close" /></button></div><FilterPanel :query="query" @apply="apply" @reset="reset" /></dialog></template>
