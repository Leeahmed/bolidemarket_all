<script setup>
import { locationLabel } from '../utils/format'
import AppIcon from './AppIcon.vue'
defineProps({ location: Object, address: String })
</script>
<template><section class="location-panel"><div class="location-illustration" aria-hidden="true"><AppIcon name="pin" /></div><div><p class="eyebrow">LOCALISATION</p><h2>{{ locationLabel(location) }}</h2><p v-if="address">{{ address }}</p><p v-if="location?.country?.name">{{ location.country.name }}</p><small>Repère illustratif, sans itinéraire ni suivi en temps réel.</small></div></section></template>
