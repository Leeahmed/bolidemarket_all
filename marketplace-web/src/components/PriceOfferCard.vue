<script setup>
import { ref,watch } from 'vue'
import { auth } from '../stores/auth'
import { get,post } from '../services/api'
import { majorToMinor } from '../utils/filters'
import { formatMoney } from '../utils/format'
import { useRealtime } from '../composables/useRealtime'
import FormError from './FormError.vue'
const props=defineProps({vehicle:Object})
const amount=ref(''),offer=ref(null),busy=ref(false),error=ref(null)
let attempt
async function reload(){if(!auth.user){offer.value=null;return}try{const r=await get('/me/price-offers',{vehicle_id:props.vehicle.id,per_page:1});offer.value=r.data[0]||null}catch(e){error.value=e}}
watch(()=>[auth.user?.id,props.vehicle.id],reload,{immediate:true})
const reconcile=useRealtime('private',e=>{if(e.type==='Reconnected'||(e.type.startsWith('PriceOffer')&&String(e.data.vehicle_id)===String(props.vehicle.id)))reconcile(reload)})
async function send(){busy.value=true;error.value=null;try{
 const minor=majorToMinor(amount.value,props.vehicle.sale_price.minor_unit)
 if(!minor||BigInt(minor)<=0n||BigInt(minor)>=BigInt(props.vehicle.sale_price.amount_minor))throw Error('Proposez un montant positif inférieur au prix affiché.')
 const body={vehicle_id:props.vehicle.id,amount_minor:minor,currency:props.vehicle.sale_price.currency}
 const encoded=JSON.stringify(body);if(!attempt||attempt.encoded!==encoded)attempt={encoded,key:crypto.randomUUID()}
 offer.value=(await post('/price-offers',body,{headers:{'Idempotency-Key':attempt.key}})).data;attempt=null
}catch(e){error.value=e}finally{busy.value=false}}
</script>
<template><section v-if="vehicle.negotiation_enabled && vehicle.inventory_status==='available'" class="content-panel negotiation-card"><p class="eyebrow">PARLONS PRIX</p><h2>Faire une proposition</h2><p>Ce vendeur accepte la négociation. Il peut accepter ou refuser votre proposition avant votre achat.</p><RouterLink v-if="!auth.user" class="button secondary" :to="{path:'/login',query:{redirect:'/vehicles/'+vehicle.slug}}">Se connecter pour proposer un prix</RouterLink><template v-else><FormError :error="error"/><div v-if="offer && ['pending','accepted'].includes(offer.status)" class="notice"><strong>{{ formatMoney({amount_minor:offer.amount_minor,currency:offer.currency,minor_unit:offer.minor_unit}) }}</strong><p>{{ offer.status==='accepted'?'Votre proposition a été acceptée.':'Votre proposition attend la réponse du vendeur.' }}</p><RouterLink v-if="offer.status==='accepted'" class="button" :to="{path:'/vehicles/'+vehicle.slug+'/buy',query:{offer:offer.id}}">Acheter à ce prix</RouterLink></div><form v-else @submit.prevent="send"><p v-if="offer?.status==='rejected'" class="help">Votre précédente proposition a été refusée. Vous pouvez en envoyer une nouvelle.</p><label>Votre prix proposé ({{ vehicle.sale_price.currency }})<input v-model="amount" inputmode="decimal" required :disabled="busy"/></label><button class="button secondary" :disabled="busy">{{ busy?'Envoi…':'Envoyer ma proposition' }}</button></form><RouterLink class="text-button" to="/account/price-offers">Suivre mes propositions →</RouterLink></template><p class="help">Proposition valable 24 h ; après acceptation, vous avez 24 h pour acheter. Le véhicule n’est pas réservé et reste soumis à disponibilité.</p></section></template>
