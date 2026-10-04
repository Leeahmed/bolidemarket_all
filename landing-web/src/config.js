export const config = Object.freeze({
  apiBase: (import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, ''),
  marketplaceUrl: import.meta.env.VITE_MARKETPLACE_URL || 'http://localhost:5174',
  merchantUrl: (import.meta.env.VITE_MARKETPLACE_URL || 'http://127.0.0.1:5174').replace(/\/$/, '') + '/pro/register',
  heroVideoUrl: import.meta.env.VITE_HERO_VIDEO_URL || '/videos/hero-abidjan.mp4',
})

export function marketplaceLink(params = {}, path = '') {
  const url = new URL(config.marketplaceUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, ''))
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value))
  })
  return url.href
}

export function searchParams({ listingType, brand, category, location }) {
  return {
    listing_type: listingType,
    ...(brand ? { brand } : {}),
    ...(category ? { category } : {}),
    ...(location?.params || {}),
  }
}
