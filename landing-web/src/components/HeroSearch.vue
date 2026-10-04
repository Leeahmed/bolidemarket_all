<script setup>
import { computed, inject, ref } from 'vue'
import { landingKey } from '../composables/useLandingData'
import { marketplaceLink, searchParams } from '../config'
import AppIcon from './AppIcon.vue'
const { filters, locations, selectedLocation, location, optionsState, loadOptions, locate, locating, geoMessage } = inject(landingKey)
const listingType = ref('sale')
const brand = ref('')
const category = ref('')
const destination = computed(() => marketplaceLink(searchParams({ listingType: listingType.value, brand: brand.value, category: category.value, location: location.value })))
function submit() { window.location.assign(destination.value) }
defineExpose({ destination })
</script>
<template>
  <div id="recherche" class="hero-search-wrap" data-reveal>
    <form class="hero-search" aria-label="Rechercher un véhicule" @submit.prevent="submit">
      <fieldset class="search-field offer-field"><legend class="sr-only">Offre</legend><AppIcon name="tag" /><div><span class="field-label" aria-hidden="true">Offre</span><div class="offer-toggle"><label :class="{ active: listingType === 'sale' }"><input v-model="listingType" type="radio" name="offer" value="sale" />Acheter</label><label :class="{ active: listingType === 'rental' }"><input v-model="listingType" type="radio" name="offer" value="rental" />Louer</label></div></div></fieldset>
      <label class="search-field"><AppIcon name="search" /><span><span class="field-label">Marque</span><select v-model="brand" name="brand" :disabled="optionsState !== 'success'"><option value="">Toutes les marques</option><option v-for="item in filters.brands" :key="item.slug" :value="item.name">{{ item.name }}</option></select></span></label>
      <label class="search-field"><AppIcon name="car" /><span><span class="field-label">Type de véhicule</span><select v-model="category" name="category" :disabled="optionsState !== 'success'"><option value="">Tous les types</option><option v-for="item in filters.categories" :key="item.slug" :value="item.slug">{{ item.label }}</option></select></span></label>
      <label class="search-field location-field"><AppIcon name="pin" /><span><span class="field-label">Localisation</span><select v-model="selectedLocation" name="location" :disabled="optionsState !== 'success'"><option value="">Toutes les localisations</option><option v-for="item in locations" :key="item.key" :value="item.key">{{ item.label }}</option></select></span></label>
      <button class="button button-primary search-submit" type="submit">Rechercher <AppIcon name="arrow" /></button>
    </form>
    <div class="search-help"><button class="quiet-button" :disabled="locating" @click="locate"><AppIcon name="pin" />{{ locating ? 'Localisation en cours…' : 'Utiliser ma position' }}</button><span v-if="geoMessage" role="status">{{ geoMessage }}</span><span v-else>Ou choisissez simplement votre ville.</span><button v-if="optionsState === 'error'" class="quiet-button retry-options" @click="loadOptions">Options indisponibles · Réessayer</button></div>
  </div>
</template>
