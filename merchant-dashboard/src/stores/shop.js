import { reactive, computed } from 'vue'
import { merchantShopService } from '../services/merchant'
export const shopState = reactive({ shops: [], id: '', ready: false, error: null })
export const currentShop = computed(() => shopState.shops.find(s => s.id === shopState.id))
export async function loadShops() {
  shopState.error = null
  try {
    let page = 1, rows = [], response
    do { response = await merchantShopService.list({ page, per_page: 100 }); rows.push(...response.data); page++ } while (page <= response.meta.last_page)
    shopState.shops = rows
    if (!rows.some(s => s.id === shopState.id)) shopState.id = rows[0]?.id || ''
    shopState.ready = true
  } catch (e) { shopState.error = e }
}
export function resetShops() { shopState.shops = []; shopState.id = ''; shopState.ready = false; shopState.error = null }
export function updateShop(shop) { shopState.shops = shopState.shops.map(s => s.id === shop.id ? shop : s) }
