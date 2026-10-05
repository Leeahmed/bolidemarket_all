<script setup>
import { useMerchantRealtime } from '../composables/useMerchantRealtime'
import { ref,reactive,onMounted } from 'vue'
import { currentShop } from '../stores/shop'
import { merchantVehicleService as api } from '../services/merchant'
import { useReferences } from '../composables/useReferences'
import { labels } from '../utils/pro'
import AsyncState from '../components/AsyncState.vue'
import VehicleTable from '../components/VehicleTable.vue'
import Pagination from '../components/Pagination.vue'
const {references}=useReferences()
const filters=reactive({q:'',status:'',listing_type:'',category_id:'',brand_id:''}),rows=ref([]),meta=ref(null),busy=ref(false),error=ref(null)
let revision=0
async function load(page=1){const n=++revision;busy.value=true;error.value=null;try{const r=await api.list({...filters,shop_id:currentShop.value?.id,page});if(n===revision){rows.value=r.data;meta.value=r.meta}}catch(e){if(n===revision)error.value=e}finally{if(n===revision)busy.value=false}}
onMounted(()=>load())
useMerchantRealtime(() => load(meta.value?.current_page || 1), e => e.type.startsWith('Vehicle'))
</script><template><div class="page-heading"><div><h1>Véhicules</h1><p class="lead">Votre parc, vos offres et vos disponibilités.</p></div><RouterLink class="button" to="/vehicles/create">Ajouter un véhicule +</RouterLink></div><form class="filterbar panel" @submit.prevent="load()"><label>Rechercher<input v-model="filters.q" type="search" placeholder="Modèle ou référence"/></label><label>Statut<select v-model="filters.status"><option value="">Tous les statuts</option><option v-for="s in ['available','rented','sold','other']" :key="s" :value="s">{{ labels[s] }}</option></select></label><label>Offre<select v-model="filters.listing_type"><option value="">Toutes les offres</option><option value="sale">Vente</option><option value="rental">Location</option></select></label><label>Catégorie<select v-model="filters.category_id"><option value="">Toutes</option><option v-for="c in references.categories" :key="c.id" :value="c.id">{{ c.label }}</option></select></label><label>Marque<select v-model="filters.brand_id"><option value="">Toutes</option><option v-for="b in references.brands" :key="b.id" :value="b.id">{{ b.name }}</option></select></label><button class="button" :disabled="busy">Filtrer</button></form><AsyncState :busy="busy" :error="error" @retry="load()"><section class="panel"><VehicleTable :vehicles="rows" @reload="load(meta?.current_page || 1)"/><Pagination :meta="meta" @page="load"/></section></AsyncState></template>