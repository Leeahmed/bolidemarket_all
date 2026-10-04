import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { locationService, shopService, vehicleService } from '../services/api'

export const landingKey = Symbol('landing')

export function useLandingData() {
  const filters = ref({ brands: [], categories: [] })
  const countries = ref([])
  const locations = ref([])
  const selectedLocation = ref('')
  const location = computed(() => locations.value.find((item) => item.key === selectedLocation.value))
  const vehicles = ref([])
  const allShops = ref([])
  const vehicleState = ref('loading')
  const shopState = ref('loading')
  const optionsState = ref('loading')
  const geoMessage = ref('')
  const locating = ref(false)
  let controller
  let requestId = 0
  let disposed = false

  async function loadVehicles() {
    const id = ++requestId
    controller?.abort()
    controller = new AbortController()
    const currentController = controller
    const timeout = setTimeout(() => currentController.abort(), 12000)
    vehicleState.value = 'loading'
    try {
      const result = await vehicleService.list({ ...(location.value?.params || {}), location_mode: 'rank', status: 'available', per_page: 4 }, controller.signal)
      if (disposed || id !== requestId) return
      vehicles.value = result.data
      vehicleState.value = result.data.length ? 'success' : 'empty'
    } catch {
      if (!disposed && id === requestId) vehicleState.value = 'error'
    } finally { clearTimeout(timeout) }
  }

  async function loadShops() {
    shopState.value = 'loading'
    try {
      const result = await shopService.list()
      if (disposed) return
      allShops.value = result.data
      shopState.value = result.data.length ? 'success' : 'empty'
    } catch { if (!disposed) shopState.value = 'error' }
  }

  async function loadOptions() {
    optionsState.value = 'loading'
    try {
      const [options, countryData, cityData, districtData] = await Promise.all([
        vehicleService.filters(), locationService.countries(), locationService.cities(), locationService.districts(),
      ])
      if (disposed) return
      filters.value = options.data
      countries.value = countryData.data
      locations.value = [
        ...districtData.data.map((district) => {
          const city = cityData.data.find((item) => item.id === district.city_id)
          return { key: `district-${district.id}`, label: `${district.name}, ${city?.name || ''}`, params: { country_code: city?.country_code, city_id: city?.id, district_id: district.id } }
        }),
        ...cityData.data.map((city) => ({ key: `city-${city.id}`, label: `${city.name}, ${city.country_code}`, params: { country_code: city.country_code, city_id: city.id } })),
        ...countryData.data.map((country) => ({ key: `country-${country.code}`, label: country.name, params: { country_code: country.code } })),
      ]
      // Initial manual demo context; never pretend the visitor has shared GPS coordinates.
      if (!selectedLocation.value) selectedLocation.value = locations.value.find((item) => item.label === 'Cocody, Abidjan')?.key || ''
      optionsState.value = 'success'
    } catch { if (!disposed) optionsState.value = 'error' }
  }

  const shops = computed(() => {
    const origin = location.value?.params || {}
    const rank = (shop) => origin.district_id && shop.location.district?.id === origin.district_id ? 0
      : origin.city_id && shop.location.city?.id === origin.city_id ? 1
        : origin.country_code && shop.location.country?.code === origin.country_code ? 2 : 3
    return [...allShops.value].sort((a, b) => rank(a) - rank(b)).slice(0, 4)
  })

  function locate() {
    if (!navigator.geolocation) { geoMessage.value = 'Géolocalisation indisponible. Choisissez une ville.'; return }
    locating.value = true
    geoMessage.value = ''
    navigator.geolocation.getCurrentPosition(({ coords }) => {
      if (disposed) return
      locations.value = [...locations.value.filter((item) => item.key !== 'gps'), { key: 'gps', label: 'Ma position', params: { latitude: coords.latitude, longitude: coords.longitude } }]
      selectedLocation.value = 'gps'
      locating.value = false
    }, () => {
      if (disposed) return
      locating.value = false
      geoMessage.value = 'Position non partagée. La sélection manuelle reste disponible.'
    }, { timeout: 10000, maximumAge: 60000, enableHighAccuracy: false })
  }

  watch(selectedLocation, loadVehicles)
  onMounted(async () => {
    loadShops()
    await loadOptions()
    if (!selectedLocation.value && !disposed) loadVehicles()
  })
  onBeforeUnmount(() => { disposed = true; controller?.abort() })
  return { filters, countries, locations, selectedLocation, location, vehicles, shops, vehicleState, shopState, optionsState, loadVehicles, loadShops, loadOptions, locate, locating, geoMessage }
}
