import { reactive } from 'vue'
import { request, post, csrfCookie } from '../services/api'
import { config } from '../config'
export const auth = reactive({ user: null, ready: false, error: null })
let pending
export async function restoreAuth(force = false) {
  if (pending) return pending
  if (auth.ready && !force) return auth.user
  pending = request('/auth/me', { quiet401: true }).then(r => { auth.user = r.data; auth.error = null; auth.ready = true; return auth.user })
    .catch(e => { auth.user = null; auth.error = e.status === 401 ? null : e; auth.ready = !auth.error })
    .finally(() => { pending = null })
  return pending
}
export async function login(fields) {
  await csrfCookie()
  const r = await post('/auth/login', fields, { quiet401: true })
  auth.user = r.data.user || r.data; auth.ready = true; auth.error = null
}
export async function logout() {
  try { await post('/auth/logout') } catch (e) { if (e.status !== 401) throw e }
  auth.user = null; auth.ready = true; auth.error = null
}
export const roleDestination = role => role === 'merchant' ? config.merchantUrl : config.marketplaceUrl
export function safeDestination(value) {
  return typeof value === 'string' && /^\/(dashboard|users|merchants|shops|vehicles|reservations|orders|payments|receipts|reports|settings|search|activity)(?:[/?#]|$)/.test(value) && !/[\\\s]/.test(value) ? value : '/dashboard'
}
export async function adminGuard(to) {
  await restoreAuth()
  if (!auth.user) return to.path === '/login' ? undefined : { path: '/login', query: { redirect: to.fullPath } }
  if (auth.user.role !== 'admin') return to.path === '/forbidden' ? undefined : '/forbidden'
  if (to.path === '/login' || to.path === '/forbidden') return safeDestination(to.query.redirect)
}
