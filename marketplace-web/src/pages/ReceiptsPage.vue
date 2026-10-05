<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { get } from '../services/api'
import { config } from '../config'
import ReceiptsBrowser from '../../../web-shared/ReceiptsBrowser.vue'
import { useRealtime } from '../composables/useRealtime'
const route=useRoute(),view=ref(null)
const reconcile=useRealtime('private',e=>{if(e.type==='Reconnected'||e.type.startsWith('Order')||e.type.startsWith('Reservation'))reconcile(()=>view.value?.reload())})
</script><template><div class="receipts-page"><ReceiptsBrowser ref="view" :get="get" :api-base="config.apiBase" scope="me" detail-base="/account/receipts" :reference="route.params.reference" /></div></template>
