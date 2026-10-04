import { get } from './api'
export const vehicleService = {
  list: (params, signal) => get('/vehicles', Object.fromEntries(Object.entries(params || {}).filter(([key]) => key !== 'market')), signal),
  detail: (slug, signal) => get(`/vehicles/${encodeURIComponent(slug)}`, {}, signal),
  filters: signal => get('/vehicles/filters', {}, signal),
}
