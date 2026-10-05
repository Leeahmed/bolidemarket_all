<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { auth } from './stores/auth'
import { currentShop } from './stores/shop'
import { realtime } from './services/realtime'
import { useRealtime } from './composables/useRealtime'
import { notify } from './stores/toasts'
watch(() => [auth.user?.id, currentShop.value?.merchant?.id], ([userId, merchantId]) => { if(userId) realtime.connect({userId, merchantId}); else realtime.disconnect() }, {immediate:true,flush:'sync'})
onBeforeUnmount(()=>realtime.disconnect())
useRealtime('merchant', event => { if(event.type==='PriceOfferCreated' && String(event.data.shop_id)===String(currentShop.value?.id)) notify('Nouvelle proposition de prix reçue.'); if(event.type==='ReservationCreated' && String(event.data.shop_id)===String(currentShop.value?.id)) notify('Nouvelle réservation reçue.') })
import { toasts,dismiss } from './stores/toasts'</script><template><RouterView/><div class="toasts" aria-live="polite"><button v-for="t in toasts" :key="t.id" @click="dismiss(t.id)">{{ t.message }} <span aria-hidden="true">×</span></button></div></template>