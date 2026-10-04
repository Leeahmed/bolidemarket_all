import { ref, onBeforeUnmount } from 'vue'
export function useLoad(loader) {
  const data = ref(null), busy = ref(false), error = ref(null)
  let revision = 0
  async function load() {
    const current = ++revision; busy.value = true; error.value = null; data.value = null
    try { const response = await loader(); if (current === revision) data.value = response.data }
    catch (e) { if (current === revision) error.value = e }
    finally { if (current === revision) busy.value = false }
  }
  onBeforeUnmount(() => { revision++ })
  return { data, busy, error, load }
}
