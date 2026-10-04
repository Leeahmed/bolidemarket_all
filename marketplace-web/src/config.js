const env = import.meta.env
const apiUrl = new URL(env.VITE_API_BASE_URL || '/api/v1', window.location.origin)
if (['localhost', '127.0.0.1'].includes(apiUrl.hostname) && ['localhost', '127.0.0.1'].includes(window.location.hostname)) apiUrl.hostname = window.location.hostname
const merchantUrl = new URL(env.VITE_MERCHANT_URL || 'http://localhost:5175', window.location.origin)
if (['localhost', '127.0.0.1'].includes(merchantUrl.hostname) && ['localhost', '127.0.0.1'].includes(window.location.hostname)) merchantUrl.hostname = window.location.hostname
export const config = Object.freeze({
  apiBase: apiUrl.href.replace(/\/$/, ''),
  landingUrl: env.VITE_LANDING_URL || '/',
  marketplaceUrl: env.VITE_MARKETPLACE_URL || '/',
  merchantUrl: merchantUrl.href.replace(/\/$/, ''),
})
