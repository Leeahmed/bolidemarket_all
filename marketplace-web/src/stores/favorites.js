import { reactive, watch } from 'vue'
import { auth } from './auth'
import { get, request } from '../services/api'
export const favorites = reactive({ items: [], ready: false, loading: false, error: null, busy: {} })
let revision = 0, pending
watch(() => auth.user?.id, () => { revision++; pending = null; favorites.items = []; favorites.ready = false; favorites.loading = false; favorites.error = null; favorites.busy = {} }, { flush: 'sync' })
export async function loadFavorites(force = false) {
  if (!auth.user) return
  if (pending) return pending
  if (favorites.ready && !force) return
  const current = revision
  favorites.loading = true; favorites.error = null
  pending = (async () => {
    try {
      const items = []
      let page = 1, last = 1
      do {
        const result = await get('/me/favorites', { page, per_page: 100 })
        items.push(...result.data); last = result.meta.last_page; page++
      } while (page <= last && current === revision)
      if (current === revision) { favorites.items = items; favorites.ready = true }
    } catch (error) { if (current === revision) favorites.error = error; throw error }
    finally { if (current === revision) { favorites.loading = false; pending = null } }
  })()
  return pending
}
export const isFavorite = id => favorites.items.some(x => String(x.id) === String(id))
export async function toggleFavorite(vehicle) {
  const id = String(vehicle.id)
  if (favorites.busy[id]) return
  favorites.busy[id] = true
  const current = revision
  try {
    await loadFavorites()
    const exists = isFavorite(id)
    const result = await request('/me/favorites/' + id, { method: exists ? 'DELETE' : 'PUT' })
    if (current !== revision) return
    favorites.items = exists ? favorites.items.filter(x => String(x.id) !== id) : [result.data, ...favorites.items]
    return !exists
  } finally { if (current === revision) delete favorites.busy[id] }
}

