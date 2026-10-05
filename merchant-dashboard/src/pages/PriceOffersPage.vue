<script setup>
import { ref,onMounted } from 'vue'
import { get,post } from '../services/api'
import { currentShop } from '../stores/shop'
import { useMerchantRealtime } from '../composables/useMerchantRealtime'
import { formatMoney } from '../utils/format'
import { notify } from '../stores/toasts'
import AsyncState from '../components/AsyncState.vue'
import Pagination from '../components/Pagination.vue'
import ConfirmDialog from '../components/ConfirmDialog.vue'
import FormError from '../components/FormError.vue'
const rows=ref([]),meta=ref(null),busy=ref(false),error=ref(null),choice=ref(null),saving=ref(false)
async function load(page=1){busy.value=true;error.value=null;try{const r=await get('/merchant/price-offers',{shop_id:currentShop.value?.id,page,per_page:20});rows.value=r.data;meta.value=r.meta}catch(e){error.value=e}finally{busy.value=false}}
useMerchantRealtime(()=>load(meta.value?.current_page||1),e=>e.type.startsWith('PriceOffer'))
onMounted(()=>load())
const labels={pending:'En attente',accepted:'Acceptée',rejected:'Refusée',expired:'Expirée',consumed:'Achat créé'}
async function respond(){saving.value=true;error.value=null;try{await post('/merchant/price-offers/'+choice.value.offer.id+'/respond',{decision:choice.value.decision});choice.value=null;notify('Réponse envoyée au client.');await load(meta.value?.current_page||1)}catch(e){error.value=e}finally{saving.value=false}}
const money=(o,key='amount_minor')=>formatMoney({amount_minor:o[key],currency:o.currency,minor_unit:o.minor_unit})
</script>
<template><h1>Propositions de prix</h1><p class="lead">Acceptez ou refusez les propositions reçues sur vos véhicules négociables.</p><AsyncState :busy="busy" :error="choice?null:error" @retry="load()"><section v-if="!rows.length" class="panel empty">Aucune proposition pour le moment.</section><article v-for="o in rows" :key="o.id" class="panel"><span class="badge">{{ labels[o.status] }}</span><h2>{{ o.vehicle.title }}</h2><p>{{ o.customer?.name }} · {{ o.shop.name }}</p><p>Prix affiché : {{ money(o,'asking_price_minor') }}</p><p class="large-price">Proposition : {{ money(o) }}</p><p class="help">Échéance : {{ new Date(o.expires_at).toLocaleString('fr-FR') }}</p><div v-if="o.status==='pending'" class="actions"><button class="button" @click="choice={offer:o,decision:'accepted'}">Accepter</button><button class="button secondary" @click="choice={offer:o,decision:'rejected'}">Refuser</button></div><p v-if="o.status==='accepted'" class="help">Le client peut acheter à ce prix pendant 24 h, sous réserve de disponibilité. Aucun paiement ni réservation du véhicule à cette étape.</p></article><Pagination :meta="meta" @page="load"/></AsyncState><ConfirmDialog :open="!!choice" :title="choice?.decision==='accepted'?'Accepter ce prix ?':'Refuser cette proposition ?'" :busy="saving" @close="choice=null" @confirm="respond"><p v-if="choice">Prix proposé : {{ money(choice.offer) }}.</p><FormError :error="error"/></ConfirmDialog></template>
