<script setup>
import { watch } from 'vue'
import { useRoute } from 'vue-router'
import { useResource } from '../composables/useResource'
import { commerceService } from '../services/commerceService'
import FormError from '../components/FormError.vue'
import CommerceRecord from '../components/CommerceRecord.vue'
import PaginationNav from '../components/PaginationNav.vue'
const route = useRoute(), { data, state, error, load } = useResource()
const reload = () => load(signal => commerceService.list(route.meta.kind, { page: route.query.page || 1, per_page: 10 }, signal))
watch(() => route.fullPath, reload, { immediate: true })
</script>
<template><p class="eyebrow">VOTRE HISTORIQUE</p><h1>{{ route.meta.kind === 'reservations' ? 'Mes réservations' : 'Mes achats' }}</h1><p class="page-lead">{{ route.meta.kind === 'reservations' ? 'Retrouvez vos locations et suivez leur statut.' : 'Vos commandes de vente et de location, au même endroit.' }}</p><p class="demo-notice">Simulation BolideMarket · Aucun paiement réel.</p><p v-if="state === 'loading'" role="status">Chargement de votre historique…</p><template v-else-if="state === 'error'"><FormError :error="error" /><button class="button" @click="reload">Réessayer</button></template><div v-else-if="!data?.data.length" class="empty-state"><h2>{{ route.meta.kind === 'reservations' ? 'Vous n’avez encore aucune réservation.' : 'Vous n’avez encore aucune commande.' }}</h2><RouterLink class="button" to="/vehicles">Explorer le marché</RouterLink></div><template v-else><div class="record-list"><CommerceRecord v-for="record in data.data" :key="record.id" :record="record" :kind="route.meta.kind" /></div><PaginationNav :meta="data.meta" @change="$router.push({ query: { ...route.query, page: $event } })" /></template></template>

