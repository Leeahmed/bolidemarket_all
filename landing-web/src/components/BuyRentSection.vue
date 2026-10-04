<script setup>
import { marketplaceLink } from '../config'
import AppIcon from './AppIcon.vue'
</script>
<template><section class="container buy-rent" aria-label="Acheter ou louer"><article class="offer-panel buy-panel" data-reveal><img src="/images/buy-auto.webp" alt="" loading="lazy" width="165" height="149" /><div><h2>ACHETER</h2><p>Trouvez le véhicule qui<br /> vous correspond.</p><a class="button button-primary" :href="marketplaceLink({ listing_type: 'sale' })">Explorer les véhicules à vendre <AppIcon /></a></div></article><article class="offer-panel rent-panel" data-reveal><img src="/images/rent-auto.webp" alt="" loading="lazy" width="176" height="149" /><div><h2>LOUER</h2><p>Une voiture pour aujourd’hui,<br /> demain ou plus longtemps.</p><a class="button button-outline" :href="marketplaceLink({ listing_type: 'rental' })">Explorer les véhicules à louer <AppIcon /></a></div></article></section></template>
