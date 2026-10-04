import { reactive } from 'vue'
import { request, post, csrfCookie } from '../services/api'
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
export function safeDestination(value) {
  return typeof value === 'string' && /^\/(dashboard|vehicles|reservations|orders|rentals|clients|shop|profile|settings)(?:[/?#]|$)/.test(value) && !/[\\\s]/.test(value) ? value : '/dashboard'
}
export async function merchantGuard(to) {
  await restoreAuth()
  if (!auth.user) return to.path === '/login' ? undefined : { path: '/login', query: { redirect: to.fullPath } }
  if (auth.user.role !== 'merchant') return to.path === '/login' ? undefined : { path: '/login', query: { forbidden: '1' } }
  if (to.path === '/login') return safeDestination(to.query.redirect)
}
