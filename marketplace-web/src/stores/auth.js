import { reactive } from 'vue'
import { appConfig, loadAppConfig } from './appConfig'
import { request, post, csrfCookie } from '../services/api'
export const auth = reactive({ user: null, ready: false, error: null })
let restoring
let revision = 0
export function clearAuth() { revision++; auth.user = null; auth.ready = true; auth.error = null }
export async function restoreAuth(force = false) {
  if (restoring) return restoring
  if (auth.ready && !force) return auth.user
  const current = revision
  restoring = (async () => {
    try {
      const result = await request('/auth/me', { quiet401: true })
      if (current === revision) { auth.user = result.data; auth.error = null; auth.ready = true }
    } catch (error) {
      if (current === revision) { auth.user = null; auth.error = error.status === 401 ? null : error; auth.ready = !auth.error }
    }
    return auth.user
  })().finally(() => { restoring = null })
  return restoring
}
export async function login(credentials) {
  await csrfCookie()
  const result = await post('/auth/login', credentials, { quiet401: true })
  // Browser authentication uses the existing HttpOnly Sanctum session; never persist a bearer token.
  revision++
  auth.user = result.data.user || result.data
  auth.ready = true
  auth.error = null
}
export async function register(fields) { await csrfCookie(); return post('/auth/register', fields) }
export async function logout() {
  try { await post('/auth/logout') } catch (error) { if (error.status !== 401) throw error }
  clearAuth()
}
export function roleHome(user = auth.user) { return user?.role === 'merchant' ? '/pro' : user?.role === 'admin' ? '/admin' : '/account' }
export function loginDestination(value) { return auth.user?.role && auth.user.role !== 'customer' ? roleHome() : safeRedirect(value) }
export function safeRedirect(value) {
  if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || [...value].some(char => char.charCodeAt(0) === 92 || char.charCodeAt(0) <= 32)) return '/account'
  const path = value.split(/[?#]/)[0]
  return /^\/(account(?:\/|$)|vehicles(?:\/|$)|shops(?:\/|$)|reservation-confirmation\/|order-confirmation\/)/.test(path) ? value : '/account'
}
export async function authGuard(to) {
  await Promise.all([restoreAuth(), loadAppConfig()])
  if (to.meta.auth && !auth.user) return { path: '/login', query: { redirect: to.fullPath } }
  if (to.meta.guest && auth.user) return loginDestination(to.query.redirect)
  if (to.meta.role && auth.user && to.meta.role !== auth.user.role) return roleHome()
  const country = auth.user?.country_code || appConfig.default_country
  if (to.path === '/vehicles' && country && !to.query.country_code && !to.query.country_id && !to.query.city_id && !to.query.latitude && to.query.market !== 'all') return { path: to.path, query: { ...to.query, country_code: country }, replace: true }
}
