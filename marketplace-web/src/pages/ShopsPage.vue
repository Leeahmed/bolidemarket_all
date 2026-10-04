<script setup>
import { watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useResource } from '../composables/useResource'
import { shopService } from '../services/shopService'
import { shopMedia } from '../utils/demoMedia'
import { locationLabel } from '../utils/format'
import SafeImage from '../components/SafeImage.vue'
import ResultState from '../components/ResultState.vue'
import PaginationNav from '../components/PaginationNav.vue'
const route = useRoute(), router = useRouter(), { data, state, error, load } = useResource()
function reload() { load(signal => shopService.list({ page: route.query.page || 1, per_page: 12 }, signal)) }
watch(() => route.query.page, reload, { immediate: true })
document.title = 'Nos professionnels | BolideMarket'
</script>
<template><section class="catalog-intro"><div class="container"><p class="eyebrow">DES PROFESSIONNELS. DES POSSIBILITÉS.</p><h1>Trouvez votre professionnel</h1><p>Explorez les boutiques et découvrez leurs véhicules à vendre ou à louer.</p></div></section><div class="container page-content"><ResultState :state="state" :error="error" :empty="!data?.data?.length" noun="professionnel" @retry="reload" @reset="router.push('/shops')"><div class="shops-grid"><article v-for="shop in data.data" :key="shop.id" class="shop-card"><RouterLink :to="`/shops/${shop.slug}`"><div class="shop-card-cover"><SafeImage :src="shopMedia(shop)" :alt="shop.name" /></div><div class="card-body"><p class="eyebrow">{{ locationLabel(shop.location) }}</p><h2>{{ shop.name }}</h2><p>{{ shop.published_vehicles_count }} véhicules publiés</p><span class="shop-link">Découvrir la boutique →</span><small v-if="shop.is_demo" class="demo-note">Boutique de démonstration</small></div></RouterLink></article></div><PaginationNav :meta="data.meta" @change="router.push({ query: { page: $event } })" /></ResultState></div></template>
