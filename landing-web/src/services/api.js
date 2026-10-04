import { config } from '../config'

export async function apiGet(path, params = {}, signal) {
  const url = new URL(`${config.apiBase}/${path}`)
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value))
  })
  const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: signal || AbortSignal.timeout(12000) })
  if (!response.ok) throw new Error(`Le catalogue est momentanément indisponible (${response.status}).`)
  return response.json()
}

export const vehicleService = {
  list: (params, signal) => apiGet('vehicles', params, signal),
  filters: () => apiGet('vehicles/filters'),
}
export const shopService = { list: () => apiGet('shops', { per_page: 100 }) }
export const locationService = {
  countries: () => apiGet('countries'),
  cities: () => apiGet('cities'),
  districts: () => apiGet('districts'),
}
