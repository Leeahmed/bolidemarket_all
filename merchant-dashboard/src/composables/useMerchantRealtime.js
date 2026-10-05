import { currentShop } from '../stores/shop'
import { useRealtime } from './useRealtime'
export function useMerchantRealtime(reload, predicate = () => true) {
  const reconcile = useRealtime('merchant', event => {
    if (event.type === 'Reconnected' || (String(event.data.shop_id) === String(currentShop.value?.id) && predicate(event))) reconcile(reload)
  })
}
