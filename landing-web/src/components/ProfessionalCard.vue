<script setup>
import { computed, ref } from 'vue'
import { marketplaceLink } from '../config'
import { shopMedia } from '../utils/demoMedia'
import { locationLabel } from '../utils/format'
import AppIcon from './AppIcon.vue'
const props = defineProps({ shop: { type: Object, required: true } })
const failed = ref(false)
const image = computed(() => shopMedia(props.shop))
const href = computed(() => marketplaceLink({}, `shops/${encodeURIComponent(props.shop.slug)}`))
</script>
<template><article class="professional-card"><a class="shop-photo" :href="href" :aria-label="`Voir ${shop.name}`"><img v-if="image && !failed" :src="image" :alt="`Illustration de démonstration : ${shop.name}`" loading="lazy" width="480" height="300" @error="failed = true" /><span v-else class="photo-placeholder"><AppIcon name="users" />{{ shop.name }}</span></a><div class="professional-body"><div class="shop-identity"><span class="avatar">{{ shop.name.split(' ').slice(0, 2).map(word => word[0]).join('') }}</span><div><h3>{{ shop.name }}</h3><p><AppIcon name="pin" />{{ locationLabel(shop.location) }}</p></div></div><p class="shop-stock"><AppIcon name="car" />{{ shop.published_vehicles_count }} véhicules publiés</p><p v-if="shop.merchant.is_verified" class="verified"><AppIcon name="check" />Professionnel vérifié</p><a class="text-link shop-link" :href="href">Voir la boutique <AppIcon /></a></div></article></template>
