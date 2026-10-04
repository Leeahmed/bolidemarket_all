<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { config } from '../config'
import { useReferences } from '../composables/useReferences'
import { cleanQuery, locationText } from '../utils/filters'
import LocationFields from './LocationFields.vue'
import AccountMenu from './AccountMenu.vue'
import AppIcon from './AppIcon.vue'
const route = useRoute(), router = useRouter(), { references } = useReferences()
const search = ref(''), menu = ref(false), dialog = ref(null), location = ref({})
watch(() => route.fullPath, () => { search.value = route.query.q || ''; menu.value = false }, { immediate: true })
const label = computed(() => locationText(route.query, references))
function searchNow() { router.push({ path: '/vehicles', query: cleanQuery({ ...route.query, q: search.value, page: '' }) }) }
function showLocation() { location.value = cleanQuery(route.query); dialog.value.showModal() }
function applyLocation() { router.push({ path: '/vehicles', query: cleanQuery({ ...location.value, page: '' }) }); dialog.value.close() }
</script>
<template><header class="app-header"><div class="header-main container"><a class="brand" :href="config.landingUrl"><img src="/images/logo-horizontal.webp" width="840" height="175" alt="BolideMarket — Achetez. Louez. Roulez." /></a><nav class="main-nav" :class="{ expanded: menu }" aria-label="Navigation principale"><RouterLink :to="{ path: '/vehicles', query: { listing_type: 'sale' } }" :class="{ active: route.query.listing_type === 'sale' }">Acheter</RouterLink><RouterLink :to="{ path: '/vehicles', query: { listing_type: 'rental' } }" :class="{ active: route.query.listing_type === 'rental' }">Louer</RouterLink><RouterLink to="/shops">Nos boutiques</RouterLink><RouterLink to="/pro/register">Professionnels</RouterLink></nav><div class="header-actions"><RouterLink to="/account/favorites" class="icon-button" aria-label="Favoris"><AppIcon name="heart" /></RouterLink><AccountMenu /><button class="icon-button menu-button" :aria-expanded="menu" aria-label="Menu" @click="menu = !menu" @keydown.esc="menu = false"><AppIcon :name="menu ? 'close' : 'menu'" /></button></div></div><div class="header-tools container"><form class="quick-search" role="search" @submit.prevent="searchNow"><AppIcon name="search" /><input v-model="search" aria-label="Recherche rapide" placeholder="Rechercher un véhicule…" maxlength="120" /><button type="submit" aria-label="Lancer la recherche"><AppIcon /></button></form><button class="header-location" @click="showLocation"><AppIcon name="pin" /><span>{{ label }}</span><AppIcon name="chevron" /></button></div></header><dialog ref="dialog" class="location-dialog"><div class="dialog-heading"><h2>Votre localisation</h2><button class="icon-button" aria-label="Fermer la localisation" @click="dialog.close()"><AppIcon name="close" /></button></div><form @submit.prevent="applyLocation"><LocationFields v-model="location" /><button class="button full" type="submit">Afficher les véhicules</button></form></dialog></template>
