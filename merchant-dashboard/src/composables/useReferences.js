import { reactive, ref } from 'vue'
import { locationService } from '../services/locationService'
const names = ['countries', 'cities', 'districts', 'brands', 'models', 'categories', 'currencies', 'features']
const references = reactive(Object.fromEntries(names.map(name => [name, []])))
const state = ref('idle'), error = ref('')
let pending
export function useReferences() {
  async function load() {
    if (pending) return pending
    state.value = 'loading'; error.value = ''
    pending = Promise.all(names.map(name => locationService.reference(name).then(result => { references[name] = result.data })))
      .then(() => { state.value = 'success' }).catch(reason => { state.value = 'error'; error.value = reason.message }).finally(() => { pending = null })
    return pending
  }
  if (state.value === 'idle') load()
  return { references, state, error, load }
}
