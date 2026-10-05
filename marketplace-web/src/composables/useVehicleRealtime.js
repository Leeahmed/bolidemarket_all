import { ref, onBeforeUnmount, watch } from 'vue'
import { vehicleService } from '../services/vehicleService'
import { useRealtime } from './useRealtime'
// Patch only visible records, preserving pagination, scroll and distance context.
export function useVehicleRealtime(data, reload, options = {}) {
  const newListings = ref(false)
  let active = true, revision = 0
  const pending = new Map(), versions = new Map()
  watch(data, () => revision++, { flush: 'sync' })
  onBeforeUnmount(() => { active = false; revision++; for (const t of pending.values()) clearTimeout(t) })
  const reconcile = useRealtime('public', event => {
    if (event.type === 'Reconnected') { reconcile(reload); return }
    if (options.shopId?.() && event.data.shop_id !== String(options.shopId())) return
    if (event.type === 'VehiclePublished' || event.type === 'VehicleCreated') { newListings.value = true; return }
    const id = event.data.vehicle_id
    if (!data.value?.data?.some(v => String(v.id) === String(id))) return
    const version = (versions.get(id) || 0) + 1; versions.set(id, version)
    const remove = () => {
      data.value.data = data.value.data.filter(v => String(v.id) !== String(id))
      if (data.value.meta) data.value.meta.total = Math.max(0, data.value.meta.total - 1)
    }
    clearTimeout(pending.get(id))
    if (event.type === 'VehicleUnpublished') { remove(); return }
    const sequence = revision
    pending.set(id, setTimeout(async () => {
      pending.delete(id)
      try {
        const result = await vehicleService.detail(event.data.slug)
        if (!active || sequence !== revision || version !== versions.get(id)) return
        // REST remains the authority for filter membership; do not invent availability rules.
        if (options.needsReconcile?.(event.data.changed_fields || [])) { reload(); return }
        data.value.data = data.value.data.map(v => String(v.id) === String(id) ? { ...result.data, distance_km: v.distance_km } : v)
      } catch (error) { if (active && sequence === revision && version === versions.get(id) && error.status === 404) remove() }
    }, 100))
  })
  return { newListings, refresh: () => { newListings.value = false; reload() } }
}
