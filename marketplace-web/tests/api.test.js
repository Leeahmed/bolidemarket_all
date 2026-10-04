import { describe, it, expect, vi, afterEach } from 'vitest'
import { request, onUnauthorized } from '../src/services/api'
afterEach(() => vi.unstubAllGlobals())
describe('Client HTTP', () => {
 it('refuse un succès HTTP dont le JSON est corrompu sans invalider la session', async () => {
   const expired = vi.fn(); onUnauthorized(expired)
   vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => { throw new SyntaxError('PHP notice') } }))
   await expect(request('/me/avatar', { method: 'POST' })).rejects.toThrow('illisible')
   expect(expired).not.toHaveBeenCalled()
 })
 it('envoie FormData sans imposer le Content-Type JSON', async () => {
   const fetch = vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({ data: {} }) })
   vi.stubGlobal('fetch', fetch)
   const body = new FormData(); body.append('avatar', new File(['image'], 'photo.png', { type: 'image/png' }))
   await request('/me/avatar', { method: 'POST', body })
   expect(fetch.mock.calls[0][1].body).toBe(body)
   expect(fetch.mock.calls[0][1].headers['Content-Type']).toBeUndefined()
 })
 it('transmet cookies, CSRF et idempotence sur les mutations', async () => {
   document.cookie = 'XSRF-TOKEN=demo%3Dvalue; path=/'
   const fetch = vi.fn().mockResolvedValue({ ok: true, status: 201, json: async () => ({ data: {} }) })
   vi.stubGlobal('fetch', fetch)
   await request('/orders', { method: 'POST', body: { vehicle_id: '1' }, headers: { 'Idempotency-Key': 'demo-key-1' } })
   expect(fetch.mock.calls[0][1]).toMatchObject({ credentials: 'include', headers: { 'X-XSRF-TOKEN': 'demo=value', 'Idempotency-Key': 'demo-key-1' } })
 })
 it('gère une réponse 204 sans tenter de décoder du JSON', async () => {
   vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 204 }))
   expect(await request('/auth/logout', { method: 'POST' })).toBeNull()
 })
 it('invalide une session expirée', async () => {
   const expired = vi.fn(); onUnauthorized(expired)
   vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 401, json: async () => ({}) }))
   await expect(request('/me/orders')).rejects.toMatchObject({ status: 401 }); expect(expired).toHaveBeenCalledOnce()
 })
 it('conserve les erreurs 422 par champ', async () => {
   vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 422, json: async () => ({ error: { fields: { email: ['Invalide'] } } }) }))
   await expect(request('/auth/register')).rejects.toMatchObject({ fields: { email: ['Invalide'] } })
 })
 it.each([403,404,409,500])('traite %s sans exposer les détails serveur', async status => {
   vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status, json: async () => ({ message: 'SQL secret stack trace' }) }))
   await expect(request('/me/orders')).rejects.not.toHaveProperty('message', 'SQL secret stack trace')
 })
})
