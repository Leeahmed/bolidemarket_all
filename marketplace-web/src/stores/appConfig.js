import { reactive } from 'vue'
import { get } from '../services/api'
export const appConfig = reactive({ demo_mode: false, default_country: null })
let pending
export function loadAppConfig() {
  return pending ||= get('/app-config').then(result => Object.assign(appConfig, result.data)).catch(() => { pending = null })
}
