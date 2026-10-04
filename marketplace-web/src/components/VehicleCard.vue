<script setup>
import { computed } from 'vue'
import { formatMoney, formatNumber, formatDistance, fuelLabel, transmissionLabel, locationLabel } from '../utils/format'
import { vehicleMedia } from '../utils/demoMedia'
import SafeImage from './SafeImage.vue'
import FavoriteButton from './FavoriteButton.vue'
import AppIcon from './AppIcon.vue'
const props = defineProps({ vehicle: { type: Object, required: true }, intention: String, origin: { type: Object, default: () => ({}) } })
const media = computed(() => vehicleMedia(props.vehicle))
const rental = computed(() => props.intention === 'rental' || !props.vehicle.sale_price)
const price = computed(() => rental.value ? props.vehicle.rental_daily_price : props.vehicle.sale_price)
const link = computed(() => ({ path: `/vehicles/${props.vehicle.slug}`, query: { ...props.origin, ...(props.intention ? { listing_type: props.intention } : {}) } }))
</script>
<template>
  <article class="vehicle-card">
    <div class="card-photo"><RouterLink :to="link" :aria-label="`Voir ${vehicle.brand.name} ${vehicle.model.name}`"><SafeImage :src="media.src" :alt="`${vehicle.brand.name} ${vehicle.model.name}`" /></RouterLink><span class="offer-badge"><i />{{ rental ? 'À louer' : 'À vendre' }}</span><FavoriteButton :vehicle="vehicle" compact /><span v-if="media.illustration" class="photo-note">Illustration démo</span></div>
    <div class="card-body"><div class="card-topline"><span v-if="vehicle.is_certified" class="certified"><AppIcon name="shield" /> Certifié</span><span v-if="!vehicle.is_available_now" class="muted">{{ { sold: 'Vendu', rented: 'Loué', other: 'Indisponible' }[vehicle.inventory_status] }}</span></div><h2><RouterLink :to="link">{{ vehicle.brand.name }} {{ vehicle.model.name }}</RouterLink></h2><p class="spec-line">{{ vehicle.year }} · {{ transmissionLabel[vehicle.transmission] }} · {{ fuelLabel[vehicle.fuel] }}</p><p class="spec-line">{{ vehicle.mileage_km == null ? 'Kilométrage non renseigné' : `${formatNumber(vehicle.mileage_km)} km` }}</p><p class="card-price">{{ formatMoney(price) }}<small v-if="rental"> / jour</small></p><p class="card-location"><AppIcon name="pin" />{{ locationLabel(vehicle.location) }}<span v-if="vehicle.distance_km != null"> · {{ formatDistance(vehicle.distance_km) }}</span></p><RouterLink v-if="vehicle.shop" class="card-seller" :to="`/shops/${vehicle.shop.slug}`"><span class="avatar">{{ vehicle.shop.name.split(' ').slice(0, 2).map(x => x[0]).join('') }}</span>{{ vehicle.shop.name }}<AppIcon /></RouterLink><small v-if="vehicle.is_demo" class="demo-note">Véhicule et prix de démonstration</small></div>
  </article>
</template>
