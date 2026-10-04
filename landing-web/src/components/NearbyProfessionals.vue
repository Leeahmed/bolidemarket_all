<script setup>
import { inject } from 'vue'
import { landingKey } from '../composables/useLandingData'
import { marketplaceLink } from '../config'
import SectionHeader from './SectionHeader.vue'
import ProfessionalCard from './ProfessionalCard.vue'
import AsyncState from './AsyncState.vue'
const { shops, shopState, loadShops } = inject(landingKey)
</script>
<template><section id="professionnels" class="section container"><SectionHeader title="Des professionnels à proximité." subtitle="Comparez les offres et découvrez la boutique de chaque professionnel." :href="marketplaceLink({}, 'shops')" link="Voir tous les professionnels" /><AsyncState :state="shopState" label="les professionnels" @retry="loadShops"><div class="cards-grid"><ProfessionalCard v-for="shop in shops" :key="shop.id" :shop="shop" /></div></AsyncState><p class="demo-note">Boutiques et illustrations de démonstration · stock issu du catalogue.</p></section></template>
