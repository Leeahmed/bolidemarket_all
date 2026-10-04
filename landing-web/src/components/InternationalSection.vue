<script setup>
import { inject } from 'vue'
import { landingKey } from '../composables/useLandingData'
const { locations, selectedLocation } = inject(landingKey)
const cities = ['Abidjan', 'Dakar', 'Paris', 'Bruxelles', 'Montréal']
function choose(city) {
  const match = locations.value.find((item) => item.key.startsWith('city-') && item.label.startsWith(city + ','))
  if (match) selectedLocation.value = match.key
  document.getElementById('recherche')?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' })
}
</script>
<template><section id="international" class="international-section"><div class="container"><h2 data-reveal>Des opportunités, où que vous soyez.</h2><div class="city-panels"><button v-for="(city, index) in cities" :key="city" :style="{ '--city-position': `${index * 25}%` }" @click="choose(city)"><span>{{ city }}</span><span aria-hidden="true">↗</span></button></div></div></section></template>
