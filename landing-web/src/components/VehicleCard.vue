<script setup>
import { computed, ref } from 'vue'
import { marketplaceLink } from '../config'
import { fuelLabel, transmissionLabel, formatMoney, formatNumber, formatDistance } from '../utils/format'
import { vehicleMedia } from '../utils/demoMedia'
import { useReveal } from '../composables/useReveal'
import AppIcon from './AppIcon.vue'
const props = defineProps({ vehicle: { type: Object, required: true }, preferredOffer: { type: String, default: '' } })
const root = ref(null)
const imageFailed = ref(false)
const rental = computed(() => props.preferredOffer === 'rental' || !props.vehicle.sale_price)
const media = computed(() => vehicleMedia(props.vehicle))
const destination = computed(() => marketplaceLink({}, `vehicles/${encodeURIComponent(props.vehicle.slug)}`))
useReveal(root)
</script>
<template>
  <article ref="root" class="vehicle-card" data-reveal>
    <a :href="destination" class="vehicle-photo" :aria-label="`Voir ${vehicle.title}`"><img v-if="media.src && !imageFailed" :src="media.src" :alt="`${media.illustration ? 'Illustration de démonstration : ' : ''}${vehicle.title}`" width="480" height="300" loading="lazy" @error="imageFailed = true" /><span v-else class="photo-placeholder"><AppIcon name="car" />Photo à venir</span><span class="offer-badge"><i />{{ rental ? 'À louer' : 'À vendre' }}</span><span class="favorite-visual" aria-hidden="true"><AppIcon name="heart" /></span></a>
    <div class="vehicle-body"><h3><a :href="destination">{{ vehicle.brand.name }} {{ vehicle.model.name }}</a></h3><p class="specs">{{ vehicle.year }} · {{ transmissionLabel[vehicle.transmission] }} · {{ fuelLabel[vehicle.fuel] }}</p><p class="specs">{{ vehicle.mileage_km == null ? 'Kilométrage non renseigné' : `${formatNumber(vehicle.mileage_km)} km` }}</p><p class="price">{{ formatMoney(rental ? vehicle.rental_daily_price : vehicle.sale_price) }}<span v-if="rental"> / jour</span></p><p class="vehicle-location"><AppIcon name="pin" />{{ vehicle.location.district?.name || vehicle.location.city?.name }}<span v-if="vehicle.distance_km != null"> · {{ formatDistance(vehicle.distance_km) }}</span></p><a class="vehicle-merchant" :href="marketplaceLink({}, `shops/${encodeURIComponent(vehicle.shop.slug)}`)"><span class="avatar">{{ vehicle.shop.name.split(' ').slice(0, 2).map(word => word[0]).join('') }}</span><span>{{ vehicle.shop.name }}</span></a></div>
  </article>
</template>
