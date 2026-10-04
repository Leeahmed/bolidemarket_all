function localUrl(value) {
  const url = new URL(value, window.location.origin)
  if (['localhost','127.0.0.1'].includes(url.hostname) && ['localhost','127.0.0.1'].includes(window.location.hostname)) url.hostname = window.location.hostname
  return url.href.replace(/\/$/, '')
}
export const config = Object.freeze({
  apiBase: localUrl(import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api/v1'),
  marketplaceUrl: localUrl(import.meta.env.VITE_MARKETPLACE_URL || 'http://localhost:5174'),
  landingUrl: localUrl(import.meta.env.VITE_LANDING_URL || 'http://localhost:5173'),
})
