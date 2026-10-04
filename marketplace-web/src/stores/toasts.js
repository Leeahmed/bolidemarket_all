import { ref } from 'vue'
export const toasts = ref([])
let next = 0
export function notify(message) {
  const id = ++next
  toasts.value.push({ id, message })
  setTimeout(() => dismiss(id), 6000)
}
export function dismiss(id) { toasts.value = toasts.value.filter(x => x.id !== id) }

