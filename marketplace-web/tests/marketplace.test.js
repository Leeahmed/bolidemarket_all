import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import fixtures from './fixtures.json'
import CatalogPage from '../src/pages/CatalogPage.vue'
import VehiclePage from '../src/pages/VehiclePage.vue'
import ShopPage from '../src/pages/ShopPage.vue'
import VehicleCard from '../src/components/VehicleCard.vue'
import LocationFields from '../src/components/LocationFields.vue'
import FilterPanel from '../src/components/FilterPanel.vue'
import SafeImage from '../src/components/SafeImage.vue'
import { locationService } from '../src/services/locationService'
import { vehicleService } from '../src/services/vehicleService'
import { shopService } from '../src/services/shopService'
import { cleanQuery, majorToMinor, locationText } from '../src/utils/filters'
import { formatMoney } from '../src/utils/format'
vi.mock('../src/services/vehicleService', () => ({ vehicleService: { list: vi.fn(), detail: vi.fn() } }))
vi.mock('../src/services/shopService', () => ({ shopService: { vehicles: vi.fn(), detail: vi.fn() } }))
vi.mock('../src/services/locationService', async importOriginal => {
  const actual = await importOriginal()
  return { locationService: { ...actual.locationService, reference: vi.fn() } }
})
const mounted = []
async function page(component, url = '/vehicles') {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/vehicles', component: CatalogPage }, { path: '/vehicles/:slug', component: VehiclePage }, { path: '/shops/:slug', component: ShopPage }] })
  await router.push(url); await router.isReady()
  const wrapper = mount(component, { global: { plugins: [router] } })
  mounted.push(wrapper); await flushPromises()
  return { wrapper, router }
}
beforeEach(() => {
  vehicleService.list.mockResolvedValue(fixtures.vehicles)
  vehicleService.detail.mockResolvedValue(fixtures.vehicle)
  shopService.detail.mockResolvedValue(fixtures.shop)
  shopService.vehicles.mockResolvedValue(fixtures.vehicles)
  locationService.reference.mockImplementation(name => Promise.resolve(fixtures[name]))
})
afterEach(() => { mounted.splice(0).forEach(x => x.unmount()); vi.useRealTimers() })
describe('Catalogue public', () => {
  it('charge le catalogue avec pagination et filtres URL', async () => {
    const { wrapper } = await page(CatalogPage, '/vehicles?listing_type=sale&brand=Toyota&page=2')
    expect(vehicleService.list).toHaveBeenCalledWith(expect.objectContaining({ listing_type: 'sale', brand: 'Toyota', page: '2', per_page: 12 }), expect.any(AbortSignal))
    expect(wrapper.findAll('.vehicle-card')).toHaveLength(fixtures.vehicles.data.length)
    expect(wrapper.find('h1').text()).toContain('Toyota à vendre')
  })
  it('synchronise recherche temporisée et retour navigateur', async () => {
    const { wrapper, router } = await page(CatalogPage)
    vi.useFakeTimers()
    const input = wrapper.get('input[aria-label="Rechercher dans le catalogue"]')
    await input.setValue('T'); await input.setValue('Toyota')
    expect(vehicleService.list).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(410); await flushPromises()
    expect(router.currentRoute.value.query.q).toBe('Toyota')
    expect(vehicleService.list).toHaveBeenLastCalledWith(expect.objectContaining({ q: 'Toyota' }), expect.any(AbortSignal))
    router.back(); await flushPromises()
    expect(router.currentRoute.value.query.q).toBeUndefined()
  })
  it('affiche un état vide et une réinitialisation', async () => {
    vehicleService.list.mockResolvedValue({ data: [], meta: { total: 0 } })
    const { wrapper, router } = await page(CatalogPage, '/vehicles?q=absent')
    expect(wrapper.text()).toContain('Aucun véhicule ne correspond à vos critères.')
    await wrapper.get('.empty-state button').trigger('click'); await flushPromises()
    expect(router.currentRoute.value.query).toEqual({})
  })
  it('affiche une erreur récupérable', async () => {
    vehicleService.list.mockRejectedValueOnce(new Error('Connexion indisponible'))
    const { wrapper } = await page(CatalogPage)
    expect(wrapper.get('[role="alert"]').text()).toContain('Connexion indisponible')
    await wrapper.get('.empty-state button').trigger('click'); await flushPromises()
    expect(wrapper.findAll('.vehicle-card').length).toBeGreaterThan(0)
  })
  it('ignore une ancienne réponse arrivée après une recherche plus récente', async () => {
    let oldResolve
    vehicleService.list.mockReturnValueOnce(new Promise(resolve => { oldResolve = resolve }))
    const { wrapper, router } = await page(CatalogPage)
    vehicleService.list.mockResolvedValueOnce({ data: [], meta: { total: 0 } })
    await router.push('/vehicles?q=absent'); await flushPromises()
    oldResolve(fixtures.vehicles); await flushPromises()
    expect(wrapper.text()).toContain('Aucun véhicule ne correspond')
  })
})
describe('Offres, prix et localisation', () => {
  it('distingue une carte de vente', async () => {
    const vehicle = fixtures.vehicles.data.find(x => x.sale_price)
    const { wrapper } = await page({ components: { VehicleCard }, setup: () => ({ vehicle }), template: '<VehicleCard :vehicle="vehicle" intention="sale" />' })
    expect(wrapper.text()).toContain('À vendre')
    expect(wrapper.get('.card-price').text()).not.toContain('/ jour')
  })
  it('affiche le tarif journalier sans le prix de vente sur une carte location', async () => {
    const vehicle = fixtures.vehicles.data.find(x => x.rental_daily_price)
    const { wrapper } = await page({ components: { VehicleCard }, setup: () => ({ vehicle }), template: '<VehicleCard :vehicle="vehicle" intention="rental" />' })
    expect(wrapper.text()).toContain('À louer')
    expect(wrapper.get('.card-price').text()).toContain('/ jour')
  })
  it('garde XOF entier et EUR/CAD en unités mineures sans conversion', () => {
    expect(majorToMinor('18500000', 0)).toBe('18500000')
    expect(majorToMinor('125,50', 2)).toBe('12550')
    expect(() => majorToMinor('12.5', 0)).toThrow()
    expect(formatMoney({ amount_minor: '12550', minor_unit: 2, currency: 'EUR' })).toContain('125,50')
    expect(formatMoney({ amount_minor: '12550', minor_unit: 2, currency: 'CAD' })).toContain('CA$125.50')
  })
  it('laisse la sélection manuelle après refus GPS, sans demander automatiquement', async () => {
    const locate = vi.fn((success, error) => error({ code: 1 }))
    Object.defineProperty(navigator, 'geolocation', { configurable: true, value: { getCurrentPosition: locate } })
    const wrapper = mount(LocationFields, { props: { modelValue: {} } }); mounted.push(wrapper)
    expect(locate).not.toHaveBeenCalled()
    await wrapper.get('button').trigger('click'); await flushPromises()
    expect(wrapper.text()).toContain('Position non autorisée')
    expect(wrapper.findAll('select')).toHaveLength(4)
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })
  it('convertit les budgets du formulaire pour une URL API exacte', async () => {
    const wrapper = mount(FilterPanel, { props: { query: { listing_type: 'sale', currency: 'EUR', min_price: '10000' } } }); mounted.push(wrapper)
    await flushPromises()
    expect(wrapper.get('input[placeholder="Minimum"]').element.value).toBe('100.00')
    await wrapper.get('input[placeholder="Maximum"]').setValue('125,50')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('apply')[0][0]).toMatchObject({ min_price: '10000', max_price: '12550', currency: 'EUR' })
  })
  it('remplace une image cassée sans boucle de requêtes', async () => {
    const wrapper = mount(SafeImage, { props: { src: '/absent.webp', alt: 'Toyota' } }); mounted.push(wrapper)
    await wrapper.get('img').trigger('error')
    expect(wrapper.find('img[src="/absent.webp"]').exists()).toBe(false)
    expect(wrapper.get('[role="img"]').attributes('aria-label')).toBe('Toyota')
  })
  it('conserve les paramètres landing et exclut les paramètres inconnus', () => {
    const refs = { countries: [], cities: [], districts: [] }
    expect(locationText({ latitude: '', longitude: '' }, refs)).toBe('Toutes les localisations')
    expect(locationText({ latitude: '0', longitude: '0' }, refs)).toBe('Votre position GPS')
    expect(cleanQuery({ listing_type: 'rental', country_id: 'CI', latitude: '0', longitude: '0', q: ' Toyota ', malicious: 'x' })).toEqual({ listing_type: 'rental', country_code: 'CI', latitude: '0', longitude: '0', q: 'Toyota' })
  })
})
describe('Pages détaillées', () => {
  it('affiche le détail, équipements et galerie sans transaction', async () => {
    const { wrapper } = await page(VehiclePage, `/vehicles/${fixtures.vehicle.data.slug}`)
    expect(wrapper.text()).toContain(fixtures.vehicle.data.model.name)
    expect(wrapper.text()).toContain('Caractéristiques')
    await wrapper.get('.gallery-main').trigger('click')
    expect(wrapper.get('dialog').attributes('open')).toBe('')
    await wrapper.get('.price-block button').trigger('click')
    expect(wrapper.get('.price-block button').attributes('disabled')).toBeDefined()
  })
  it('filtre le parc de boutique sur le serveur et remet la pagination à zéro', async () => {
    const { wrapper, router } = await page(ShopPage, `/shops/${fixtures.shop.data.slug}?page=2`)
    expect(wrapper.text()).toContain(fixtures.shop.data.name)
    await wrapper.findAll('.offer-tabs button')[2].trigger('click'); await flushPromises()
    expect(shopService.vehicles).toHaveBeenLastCalledWith(fixtures.shop.data.slug, expect.objectContaining({ listing_type: 'rental', page: 1 }), expect.any(AbortSignal))
    expect(router.currentRoute.value.query).toEqual({ tab: 'rental' })
  })
})
