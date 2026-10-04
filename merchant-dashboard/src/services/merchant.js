import { get, request, post } from './api'
const resource = name => ({
  list: params => get('/merchant/' + name, params),
  get: id => get('/merchant/' + name + '/' + encodeURIComponent(id)),
  create: body => post('/merchant/' + name, body),
  update: (id, body) => request('/merchant/' + name + '/' + encodeURIComponent(id), { method: 'PUT', body }),
  remove: id => request('/merchant/' + name + '/' + encodeURIComponent(id), { method: 'DELETE' }),
  action: (id, action) => post('/merchant/' + name + '/' + encodeURIComponent(id) + '/' + action),
})
export const merchantDashboardService = { get: shop_id => get('/merchant/dashboard', { shop_id }), clients: params => get('/merchant/clients', params) }
export const merchantVehicleService = {
  ...resource('vehicles'),
  status: (id, body) => request('/merchant/vehicles/' + id + '/status', { method: 'PATCH', body }),
  upload: (id, file) => { const body = new FormData(); body.append('image', file); return post('/merchant/vehicles/' + id + '/images', body) },
  removeImage: (id, image) => request('/merchant/vehicles/' + id + '/images/' + image, { method: 'DELETE' }),
  primary: (id, image) => request('/merchant/vehicles/' + id + '/images/' + image + '/primary', { method: 'PATCH', body: {} }),
}
export const merchantReservationService = resource('reservations')
export const merchantOrderService = resource('orders')
export const merchantShopService = {
  ...resource('shops'),
  media: (id, kind, file) => { const body = new FormData(); body.append('kind', kind); body.append('image', file); return post('/merchant/shops/' + id + '/media', body) },
}
