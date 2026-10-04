import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import App from '../src/App.vue'
import HeroSearch from '../src/components/HeroSearch.vue'
import VehicleCard from '../src/components/VehicleCard.vue'
import ProfessionalCard from '../src/components/ProfessionalCard.vue'
import { landingKey } from '../src/composables/useLandingData'
import { locationService, shopService, vehicleService } from '../src/services/api'
import { formatMoney } from '../src/utils/format'
import { vehicle, shop, countries, cities, districts, filters } from './fixtures'

vi.mock('../src/services/api', () => ({
  vehicleService: { list: vi.fn(), filters: vi.fn() }, shopService: { list: vi.fn() },
  locationService: { countries: vi.fn(), cities: vi.fn(), districts: vi.fn() },
}))
let wrapper
beforeEach(() => {
  vehicleService.list.mockResolvedValue({ data: [vehicle] })
  vehicleService.filters.mockResolvedValue({ data: filters })
  shopService.list.mockResolvedValue({ data: [shop] })
  locationService.countries.mockResolvedValue({ data: countries })
  locationService.cities.mockResolvedValue({ data: cities })
  locationService.districts.mockResolvedValue({ data: districts })
})
afterEach(() => { wrapper?.unmount(); vi.clearAllMocks() })

describe('Landing', () => {
  it('renders Home, ordered sections, official logo and 2026 copyright', async () => {
    wrapper = mount(App)
    await flushPromises()
    expect(wrapper.findAll('h1')).toHaveLength(1)
    expect(wrapper.get('h1').text()).toContain('Votre prochain bolide')
    expect(wrapper.get('.brand img').attributes('src')).toBe('/images/logo-horizontal.webp')
    expect(wrapper.text()).toContain('© 2026 BolideMarket')
    const headings = wrapper.findAll('main h2').map(item => item.text())
    expect(headings.indexOf('Près de vous')).toBeLessThan(headings.indexOf('Des professionnels à proximité.'))
    expect(headings.indexOf('Des professionnels à proximité.')).toBeLessThan(headings.indexOf('Vous vendez ou louez des véhicules ?'))
  })
  it('loads vehicles from API with real reference IDs and no fabricated GPS', async () => {
    wrapper = mount(App)
    await flushPromises()
    expect(vehicleService.list).toHaveBeenCalledWith(expect.objectContaining({ district_id: '3', city_id: '7', location_mode: 'rank', per_page: 4 }), expect.any(AbortSignal))
    expect(vehicleService.list.mock.calls[0][0]).not.toHaveProperty('latitude')
    expect(wrapper.findAll('.vehicle-card')).toHaveLength(1)
    expect(wrapper.get('.vehicle-card').text()).toContain('Toyota RAV4')
  })
  it('shows API error and retries without losing manual context', async () => {
    vehicleService.list.mockRejectedValueOnce(new Error('offline'))
    wrapper = mount(App)
    await flushPromises()
    expect(wrapper.get('#vehicules [role="alert"]').text()).toContain('Impossible de charger')
    await wrapper.get('#vehicules .feedback button').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('.vehicle-card')).toHaveLength(1)
  })
  it('shows loading skeletons and a useful empty state', async () => {
    vehicleService.list.mockResolvedValue({ data: [] })
    wrapper = mount(App)
    expect(wrapper.findAll('.skeleton-card').length).toBeGreaterThan(0)
    await flushPromises()
    expect(wrapper.get('#vehicules .feedback').text()).toContain('Aucune offre')
    expect(wrapper.get('#vehicules .feedback a').attributes('href')).toBe('#recherche')
  })
  it('manual country change updates vehicle request', async () => {
    wrapper = mount(App)
    await flushPromises()
    await wrapper.get('.footer-preferences select').setValue('FR')
    await flushPromises()
    expect(vehicleService.list).toHaveBeenLastCalledWith(expect.objectContaining({ country_code: 'FR' }), expect.any(AbortSignal))
    expect(vehicleService.list.mock.calls.at(-1)[0]).not.toHaveProperty('district_id')
  })
  it('mobile menu is keyboard closable and store buttons explain availability', async () => {
    wrapper = mount(App)
    await wrapper.get('.menu-toggle').trigger('click')
    expect(wrapper.get('.menu-toggle').attributes('aria-expanded')).toBe('true')
    await wrapper.get('header').trigger('keydown', { key: 'Escape' })
    expect(wrapper.get('.menu-toggle').attributes('aria-expanded')).toBe('false')
    await wrapper.get('.store-buttons button').trigger('click')
    expect(wrapper.get('#application [role="status"]').text()).toContain('prochainement')
  })
})

it('HeroSearch creates sale/rental, brand, category and location parameters', async () => {
  const context = { filters: ref(filters), locations: ref([{ key: 'cocody', label: 'Cocody, Abidjan' }]), selectedLocation: ref('cocody'),
    location: ref({ params: { country_code: 'CI', city_id: '7', district_id: '3' } }), optionsState: ref('success'), loadOptions: vi.fn(), locate: vi.fn(), locating: ref(false), geoMessage: ref('') }
  wrapper = mount(HeroSearch, { global: { provide: { [landingKey]: context } } })
  expect(new URL(wrapper.vm.destination).searchParams.get('listing_type')).toBe('sale')
  await wrapper.get('input[value="rental"]').setValue()
  await wrapper.get('select[name="brand"]').setValue('Toyota')
  await wrapper.get('select[name="category"]').setValue('suv')
  const params = Object.fromEntries(new URL(wrapper.vm.destination).searchParams)
  expect(params).toEqual({ listing_type: 'rental', brand: 'Toyota', category: 'suv', country_code: 'CI', city_id: '7', district_id: '3' })
})

it('VehicleCard sale renders API price, metadata, distance and professional', () => {
  wrapper = mount(VehicleCard, { props: { vehicle } })
  const text = wrapper.text().replace(/\s/g, ' ')
  expect(text).toContain('À vendre')
  expect(text).toContain('18 500 000 FCFA')
  expect(text).toContain('2,4 km')
  expect(text).not.toContain('/ jour')
})
it('VehicleCard rental uses daily price and omits unknown distance', () => {
  wrapper = mount(VehicleCard, { props: { vehicle: { ...vehicle, sale_price: null, distance_km: null, rental_daily_price: { amount_minor: '45000', currency: 'XOF', minor_unit: 0, unit: 'day' } } } })
  expect(wrapper.text().replace(/\s/g, ' ')).toContain('45 000 FCFA / jour')
  expect(wrapper.text()).toContain('À louer')
  expect(wrapper.find('.vehicle-location span').exists()).toBe(false)
})
it('ProfessionalCard shows actual stock and never invents ratings or verification', () => {
  wrapper = mount(ProfessionalCard, { props: { shop } })
  expect(wrapper.text()).toContain('3 véhicules publiés')
  expect(wrapper.text()).not.toContain('avis')
  expect(wrapper.find('.verified').exists()).toBe(false)
})
it('money formatter respects EUR minor units and XOF integers', () => {
  expect(formatMoney({ amount_minor: '2890000', currency: 'EUR', minor_unit: 2 }).replace(/\s/g, ' ')).toBe('28 900 €')
  expect(formatMoney({ amount_minor: '45000', currency: 'XOF', minor_unit: 0 }).replace(/\s/g, ' ')).toBe('45 000 FCFA')
})
