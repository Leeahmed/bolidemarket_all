import { onBeforeUnmount, ref } from 'vue'
export function useResource() {
  const data = ref(null), state = ref('loading'), error = ref(null)
  let controller, sequence = 0
  async function load(request) {
    const current = ++sequence
    controller?.abort(); controller = new AbortController()
    state.value = 'loading'; error.value = null
    try { const result = await request(controller.signal); if (current === sequence) { data.value = result; state.value = 'success' } }
    catch (reason) { if (current === sequence && !controller.signal.aborted) { error.value = reason; state.value = 'error' } }
  }
  onBeforeUnmount(() => { sequence++; controller?.abort() })
  return { data, state, error, load }
}
