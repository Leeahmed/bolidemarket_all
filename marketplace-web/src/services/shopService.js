import { get } from './api'
export const shopService = {
  list: (params, signal) => get('/shops', params, signal),
  detail: (slug, signal) => get(`/shops/${encodeURIComponent(slug)}`, {}, signal),
  vehicles: (slug, params, signal) => get(`/shops/${encodeURIComponent(slug)}/vehicles`, params, signal),
}
