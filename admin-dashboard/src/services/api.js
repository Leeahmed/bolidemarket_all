import { config } from '../config'
export function queryString(params = {}) {
  return new URLSearchParams(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined)).toString()
}
let unauthorized = () => {}
export function onUnauthorized(handler) { unauthorized = handler }
const messages = {
  401: 'Votre session a expiré. Reconnectez-vous pour continuer.',
  403: 'Cette action n’est pas autorisée. Vérifiez votre compte et votre adresse e-mail.',
  404: 'Cette ressource est introuvable ou n’est plus accessible.',
  409: 'La situation a changé. Actualisez les informations avant de continuer.',
  419: 'Votre session a expiré. Rechargez la page puis réessayez.',
  422: 'Vérifiez les champs indiqués.',
  429: 'Trop de tentatives. Patientez un instant avant de réessayer.',
}
const conflicts = {
  NEGOTIATION_DISABLED: 'Le vendeur n’accepte plus de propositions pour cette annonce.',
  OFFER_EXISTS: 'Une proposition est déjà en cours pour ce véhicule. Consultez vos propositions.',
  OFFER_UNAVAILABLE: 'Cette proposition a expiré ou n’est plus utilisable. Consultez vos propositions.',
  OFFER_STALE: 'L’annonce a changé. Une nouvelle proposition est nécessaire.',
  VEHICLE_UNAVAILABLE: 'Ce véhicule n’est plus disponible pour cette demande.',
  QUOTE_EXPIRED: 'Le devis a expiré. Demandez un nouveau résumé.',
  PRICE_CHANGED: 'Le tarif a changé. Vérifiez le nouveau prix avant de continuer.',
  INVALID_TRANSITION: 'Cette opération n’est plus possible dans l’état actuel.',
  IDEMPOTENCY_CONFLICT: 'Cette demande a déjà été envoyée avec un autre contenu.',
}
export async function request(path, { method = 'GET', params = {}, body, signal, headers = {}, quiet401 = false, absolute = false } = {}) {
  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), 15000)
  const abort = () => controller.abort()
  signal?.addEventListener('abort', abort, { once: true })
  if (signal?.aborted) controller.abort()
  const csrf = document.cookie.split('; ').find(x => x.startsWith('XSRF-TOKEN='))?.slice(11)
  try {
    const url = (absolute ? path : config.apiBase + path) + (queryString(params) ? '?' + queryString(params) : '')
    const response = await fetch(url, {
      method, credentials: 'include', signal: controller.signal,
      headers: { Accept: 'application/json', ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}), ...(csrf && method !== 'GET' ? { 'X-XSRF-TOKEN': decodeURIComponent(csrf) } : {}), ...headers },
      ...(body ? { body: body instanceof FormData ? body : JSON.stringify(body) } : {}),
    })
    if (response.status === 204) return null
    const data = await response.json().catch(() => {
      if (response.ok) throw new Error('La réponse du serveur est illisible. Rechargez la page puis réessayez.')
      return {}
    })
    if (!response.ok) {
      const error = new Error(response.status >= 500 ? 'Le service est momentanément indisponible. Réessayez.' : conflicts[data.error?.code] || messages[response.status] || 'La demande n’a pas pu aboutir.')
      error.status = response.status
      error.code = data.error?.code
      error.fields = response.status === 422 ? data.error?.fields || data.errors || {} : {}
      if (response.status === 401 && !quiet401) unauthorized()
      throw error
    }
    return data
  } catch (error) {
    if (signal?.aborted) throw error
    if (error.name === 'AbortError') throw new Error('Le serveur met trop de temps à répondre. Réessayez.')
    if (error instanceof TypeError) throw new Error('Connexion au service impossible. Réessayez dans un instant.')
    throw error
  } finally { clearTimeout(timeout); signal?.removeEventListener('abort', abort) }
}
export const get = (path, params = {}, signal) => request(path, { params, signal })
export const post = (path, body = {}, options = {}) => request(path, { ...options, method: 'POST', body })
export const csrfCookie = () => request(new URL(config.apiBase, window.location.origin).origin + '/sanctum/csrf-cookie', { absolute: true, quiet401: true })
