<script setup>
import { onMounted } from 'vue'
import { get } from '../services/api'
import { useResource } from '../composables/useResource'
import { useRealtime } from '../composables/useRealtime'
import { formatMoney } from '../utils/format'
import ResultState from '../components/ResultState.vue'
import PaginationNav from '../components/PaginationNav.vue'
const {data,state,error,load}=useResource()
let page=1
function reload(p=page){page=p;return load(signal=>get('/me/price-offers',{page,per_page:10},signal))}
const reconcile=useRealtime('private',e=>{if(e.type==='Reconnected'||e.type.startsWith('PriceOffer'))reconcile(()=>reload())})
onMounted(()=>reload())
const labels={pending:'En attente',accepted:'Acceptée',rejected:'Refusée',expired:'Expirée',consumed:'Utilisée pour un achat'}
</script>
<template><h1>Mes propositions</h1><p class="page-lead">Les réponses des vendeurs à vos propositions de prix.</p><ResultState :state="state" :error="error" :empty="false" @retry="reload()"><section v-if="!data?.data?.length" class="content-panel"><h2>Aucune proposition pour le moment.</h2><p>Vous pouvez proposer un prix sur les annonces dont le vendeur accepte la négociation.</p><RouterLink class="button secondary" to="/vehicles">Voir les véhicules</RouterLink></section><article v-for="o in data?.data || []" :key="o.id" class="content-panel"><span class="pill">{{ labels[o.status] }}</span><h2>{{ o.vehicle.title }}</h2><p>{{ o.shop.name }}</p><p class="record-price">{{ formatMoney({amount_minor:o.amount_minor,currency:o.currency,minor_unit:o.minor_unit}) }}</p><p class="help">Échéance : {{ new Date(o.expires_at).toLocaleString('fr-FR') }} · Sous réserve de disponibilité.</p><RouterLink v-if="o.status==='accepted'" class="button" :to="{path:'/vehicles/'+o.vehicle.slug+'/buy',query:{offer:o.id}}">Acheter au prix accepté</RouterLink><RouterLink v-else class="text-button" :to="'/vehicles/'+o.vehicle.slug">Voir le véhicule →</RouterLink></article><PaginationNav :meta="data?.meta" @change="reload"/></ResultState></template>
