<script setup>
import { provide, ref } from 'vue'
import { landingKey, useLandingData } from './composables/useLandingData'
import { useReveal } from './composables/useReveal'
import AppHeader from './components/AppHeader.vue'
import HeroSection from './components/HeroSection.vue'
import NearbyVehicles from './components/NearbyVehicles.vue'
import BuyRentSection from './components/BuyRentSection.vue'
import VehicleCategories from './components/VehicleCategories.vue'
import NearbyProfessionals from './components/NearbyProfessionals.vue'
import MapSection from './components/MapSection.vue'
import HowItWorks from './components/HowItWorks.vue'
import ProSection from './components/ProSection.vue'
import MobileAppSection from './components/MobileAppSection.vue'
import InternationalSection from './components/InternationalSection.vue'
import StatsSection from './components/StatsSection.vue'
import FinalCTA from './components/FinalCTA.vue'
import AppFooter from './components/AppFooter.vue'
provide(landingKey, useLandingData())
const sections = ref(null)
useReveal(sections)
</script>
<template><a class="skip-link" href="#contenu">Aller au contenu</a><AppHeader /><main id="contenu"><HeroSection /><div ref="sections"><NearbyVehicles /><BuyRentSection /><VehicleCategories /><NearbyProfessionals /><MapSection /><HowItWorks /><ProSection /><MobileAppSection /><InternationalSection /><StatsSection /><FinalCTA /></div></main><AppFooter /></template>
