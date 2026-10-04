import { get, post } from './api'
export const commerceService = {
  list: (kind, params = {}, signal) => get('/me/' + kind, params, signal),
  detail: (kind, id, signal) => get('/me/' + kind + '/' + encodeURIComponent(id), {}, signal),
  cancel: id => post('/me/reservations/' + id + '/cancel'),
  quote: body => post('/rental-quotes', body),
  reserve: (body, key) => post('/reservations', body, { headers: { 'Idempotency-Key': key } }),
  order: (body, key) => post('/orders', body, { headers: { 'Idempotency-Key': key } }),
  async availability(slug, params = {}) {
    let page = 1, result, data
    do {
      result = await get('/vehicles/' + encodeURIComponent(slug) + '/availability', { ...params, page, per_page: 100 })
      if (!data) data = { ...result.data, intervals: [] }
      data.intervals.push(...result.data.intervals)
      page++
    } while (page <= result.meta.last_page)
    return data
  },
}

