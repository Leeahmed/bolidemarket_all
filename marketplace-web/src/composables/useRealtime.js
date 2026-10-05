import { onBeforeUnmount } from 'vue'
import { realtime } from '../services/realtime'
export function useRealtime(scope, callback) {
  let active = true, timer
  const off = realtime.on(scope, event => {
    if (active) return callback(event)
  })
  onBeforeUnmount(() => { active = false; clearTimeout(timer); off() })
  return function reconcile(callback) {
    clearTimeout(timer)
    timer = setTimeout(() => { if (active) Promise.resolve(callback()).catch(() => {}) }, 120)
  }
}
