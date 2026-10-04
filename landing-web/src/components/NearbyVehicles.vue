<script setup>
import { inject } from 'vue'
import { landingKey } from '../composables/useLandingData'
import { marketplaceLink } from '../config'
import SectionHeader from './SectionHeader.vue'
import AsyncState from './AsyncState.vue'
import VehicleCard from './VehicleCard.vue'
const { vehicles, vehicleState, location, loadVehicles } = inject(landingKey)
</script>
<template><section id="vehicules" class="section container" aria-labelledby="vehicles-title"><SectionHeader title="Près de vous" :subtitle="`Découvrez les véhicules disponibles${location ? ` autour de ${location.label}` : ', près de chez vous et ailleurs'}.`" :href="marketplaceLink(location?.params)" link="Voir tous les véhicules" /><h2 id="vehicles-title" class="sr-only">Véhicules près de vous</h2><AsyncState :state="vehicleState" label="les véhicules" @retry="loadVehicles"><div class="cards-grid"><VehicleCard v-for="vehicle in vehicles" :key="vehicle.id" :vehicle="vehicle" /></div></AsyncState><p class="demo-note">Véhicules, illustrations et prix de démonstration. Distances affichées uniquement avec une position partagée.</p></section></template>
