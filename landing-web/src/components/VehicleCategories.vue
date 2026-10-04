<script setup>
import { computed, inject, ref } from 'vue'
import { landingKey } from '../composables/useLandingData'
import { marketplaceLink } from '../config'
import AppIcon from './AppIcon.vue'
const { filters } = inject(landingKey)
const track = ref(null)
const active = ref('suv')
const editorial = [
  { slug: 'suv', label: 'SUV', image: 'vehicle-rav4', caption: 'Polyvalence au quotidien' },
  { slug: 'sedan', label: 'Berline', image: 'vehicle-c300', caption: 'Le confort, en mouvement' },
  { slug: 'city', label: 'Citadine', image: 'vehicle-208', caption: 'Pratique et économique' },
  { slug: 'four_by_four', label: '4×4', image: 'buy-auto', caption: 'Prenez un autre chemin' },
  { slug: 'sport', label: 'Sport', image: 'final-auto', caption: 'Le plaisir de conduire' },
  { slug: 'luxury', label: 'Luxe', image: 'vehicle-c300', caption: 'Une autre idée du voyage' },
  { slug: 'utility', label: 'Utilitaire', image: 'vehicle-kangoo', caption: 'L’efficacité pour les pros' },
  { slug: 'electric', label: 'Électrique', image: 'vehicle-electric', caption: 'L’innovation en mouvement' },
]
const categories = computed(() => editorial.filter((item) => item.slug === 'electric' || filters.value.categories.some((category) => category.slug === item.slug)))
function choose(slug) { active.value = slug; document.getElementById(`category-${slug}`)?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'nearest', inline: 'start' }) }
function scroll(direction) { track.value?.scrollBy({ left: direction * (track.value.clientWidth * 0.65), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' }) }
</script>
<template><section id="categories" class="section container categories" aria-labelledby="categories-title"><h2 id="categories-title" data-reveal>Un bolide pour chaque envie.</h2><div class="category-tabs" aria-label="Choisir un type de véhicule"><button v-for="item in categories" :key="item.slug" :class="{ active: active === item.slug }" :aria-pressed="active === item.slug" @click="choose(item.slug)">{{ item.label }}</button></div><div class="carousel-shell"><button class="carousel-arrow previous icon-button" aria-label="Catégories précédentes" @click="scroll(-1)"><AppIcon /></button><div ref="track" class="category-track" tabindex="0" aria-label="Parcourir les catégories" @keydown.right.prevent="scroll(1)" @keydown.left.prevent="scroll(-1)"><a v-for="item in categories" :id="`category-${item.slug}`" :key="item.slug" class="category-card" :href="marketplaceLink(item.slug === 'electric' ? { fuel_type: 'electric' } : { category: item.slug })"><img :src="`/images/${item.image}.webp`" alt="" loading="lazy" width="400" height="300" /><div><h3>{{ item.label }}</h3><p>{{ item.caption }}</p></div></a></div><button class="carousel-arrow next icon-button" aria-label="Catégories suivantes" @click="scroll(1)"><AppIcon /></button></div><p class="demo-note">Illustrations issues des maquettes · explorez les offres par catégorie.</p></section></template>
