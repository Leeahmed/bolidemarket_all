import { get } from './api'
export const locationService = {
  reference: (name, params = {}, signal) => get(`/${name}`, params, signal),
  locate: () => new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('La géolocalisation est indisponible. Choisissez votre ville manuellement.'))
    navigator.geolocation.getCurrentPosition(position => resolve({ latitude: String(position.coords.latitude), longitude: String(position.coords.longitude) }), error => reject(new Error(error.code === 1 ? 'Position non autorisée. Vous pouvez choisir votre ville manuellement.' : 'Position introuvable. Choisissez votre ville manuellement.')), { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 })
  }),
}
