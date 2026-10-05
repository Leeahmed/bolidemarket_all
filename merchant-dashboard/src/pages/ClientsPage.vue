<script setup>
import { useMerchantRealtime } from '../composables/useMerchantRealtime'
import { ref,onMounted } from 'vue'
import { currentShop } from '../stores/shop'
import { merchantDashboardService } from '../services/merchant'
import { date } from '../utils/pro'
import AsyncState from '../components/AsyncState.vue'
import Pagination from '../components/Pagination.vue'
const rows=ref([]),meta=ref(null),busy=ref(false),error=ref(null),q=ref('')
async function load(page=1){if(!currentShop.value)return;busy.value=true;error.value=null;try{const r=await merchantDashboardService.clients({shop_id:currentShop.value.id,page,q:q.value});rows.value=r.data;meta.value={total:r.total,current_page:r.current_page,last_page:r.last_page}}catch(e){error.value=e}finally{busy.value=false}}
onMounted(()=>load())
useMerchantRealtime(() => load(meta.value?.current_page || 1), e => /^(Reservation|Order)/.test(e.type))
</script><template><h1>Clients</h1><p class="lead">Les personnes ayant effectué une demande auprès de votre boutique.</p><form class="filterbar panel" @submit.prevent="load()"><label>Rechercher<input v-model="q" type="search" placeholder="Nom ou e-mail"/></label><button class="button">Rechercher</button></form><AsyncState :busy="busy" :error="error" @retry="load()"><section class="panel"><div v-if="!rows.length" class="empty"><h2>Aucun client à afficher.</h2><p>Vos clients apparaîtront après leur première demande.</p></div><div v-else class="table-wrap"><table><thead><tr><th>Nom</th><th>E-mail</th><th>Téléphone</th><th>Opérations</th><th>Dernière activité</th></tr></thead><tbody><tr v-for="c in rows" :key="c.id"><td><strong>{{ c.name }}</strong></td><td>{{ c.email }}</td><td>{{ c.phone || '—' }}</td><td>{{ c.operations_count }}</td><td>{{ date(c.last_activity) }}</td></tr></tbody></table></div><Pagination :meta="meta" @page="load"/></section></AsyncState></template>