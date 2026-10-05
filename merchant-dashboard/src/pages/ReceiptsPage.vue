<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { get } from '../services/api'
import { config } from '../config'
import ReceiptsBrowser from '../../../web-shared/ReceiptsBrowser.vue'
import { currentShop } from '../stores/shop'
import { useMerchantRealtime } from '../composables/useMerchantRealtime'
const route=useRoute(),view=ref(null)
useMerchantRealtime(()=>view.value?.reload(),e=>e.type.startsWith('Order')||e.type.startsWith('Reservation'))
</script><template><div ><ReceiptsBrowser ref="view" :get="get" :api-base="config.apiBase" scope="merchant" detail-base="/receipts" :reference="route.params.reference" :shop-id="currentShop?.id"/></div></template>
