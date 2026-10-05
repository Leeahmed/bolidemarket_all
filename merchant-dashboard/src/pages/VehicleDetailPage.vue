<script setup>
import { useMerchantRealtime } from '../composables/useMerchantRealtime'
import { onMounted } from 'vue'
import { useRoute,useRouter } from 'vue-router'
import { useLoad } from '../composables/useLoad'
import { merchantVehicleService as api } from '../services/merchant'
import { formatMoney,locationLabel,fuelLabel,transmissionLabel } from '../utils/format'
import { labels,offerLabel } from '../utils/pro'
import { config } from '../config'
import { vehicleMedia } from '../utils/demoMedia'
import AsyncState from '../components/AsyncState.vue'
import SafeImage from '../components/SafeImage.vue'
import VehicleActions from '../components/VehicleActions.vue'
const route=useRoute(),router=useRouter(),{data:v,busy,error,load}=useLoad(()=>api.get(route.params.id))
onMounted(load)
useMerchantRealtime(() => load(true), e => e.type.startsWith('Vehicle') && String(e.data.vehicle_id)===String(route.params.id))
</script><template><RouterLink class="back" to="/vehicles">← Retour au parc</RouterLink><AsyncState :busy="busy" :error="error" @retry="load"><template v-if="v"><div class="page-heading"><div><p class="eyebrow">{{ v.reference }}</p><h1>{{ v.title }}</h1><p>{{ locationLabel(v.location) }}</p></div><VehicleActions :vehicle="v" @updated="load" @deleted="router.push('/vehicles')"/></div><div class="detail-grid"><div><SafeImage class="detail-photo" :src="vehicleMedia(v).src" :alt="v.title"/><div class="photo-grid"><SafeImage v-for="photo in v.images.filter(p=>!p.is_placeholder)" :key="photo.id" :src="photo.url" :alt="photo.alt_text || v.title"/></div></div><section class="panel"><span class="badge">{{ labels[v.inventory_status] }}</span> <span class="badge">{{ labels[v.publication_status] }}</span><h2>{{ offerLabel(v) }}</h2><p v-if="v.sale_price" class="large-price">{{ formatMoney(v.sale_price) }}</p><p v-if="v.rental_daily_price" class="large-price">{{ formatMoney(v.rental_daily_price) }} / jour</p><dl class="facts"><template v-for="[label,value] in [['Année',v.year],['Kilométrage',v.mileage_km],['Motorisation',fuelLabel[v.fuel]],['Transmission',transmissionLabel[v.transmission]],['Couleur',v.color],['Places',v.seats],['Moteur',v.engine],['Puissance',v.horsepower]]" :key="label"><dt>{{ label }}</dt><dd>{{ value ?? '—' }}</dd></template></dl><a v-if="v.publication_status==='published'" class="button secondary" :href="config.marketplaceUrl+'/vehicles/'+v.slug" target="_blank" rel="noopener">Voir l’annonce publique ↗</a></section></div><section class="panel"><h2>Description</h2><p class="preserve">{{ v.description }}</p><div class="actions"><span v-for="f in v.features" :key="f.id" class="badge">{{ f.label }}</span></div></section></template></AsyncState></template>