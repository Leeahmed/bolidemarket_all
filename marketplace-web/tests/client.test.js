import { beforeEach, afterEach, describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { nextTick } from 'vue'
import fixtures from './fixtures.json'
import { auth, clearAuth, login, restoreAuth, authGuard, safeRedirect } from '../src/stores/auth'
import { request, post, csrfCookie, get } from '../src/services/api'
import { favorites, loadFavorites } from '../src/stores/favorites'
import { commerceService } from '../src/services/commerceService'
import { vehicleService } from '../src/services/vehicleService'
import { rangeAvailable, canCancelReservation, zonedMidnight } from '../src/utils/commerce'
import AuthPage from '../src/pages/AuthPage.vue'
import FavoriteButton from '../src/components/FavoriteButton.vue'
import DateCalendar from '../src/components/DateCalendar.vue'
import CheckoutPage from '../src/pages/CheckoutPage.vue'
import TransactionActions from '../src/components/TransactionActions.vue'
import RecordPage from '../src/pages/RecordPage.vue'
vi.mock('../src/services/api', () => ({ request: vi.fn(), post: vi.fn(), csrfCookie: vi.fn(), get: vi.fn() }))
vi.mock('../src/services/commerceService', () => ({ commerceService: { availability: vi.fn(), quote: vi.fn(), reserve: vi.fn(), order: vi.fn(), detail: vi.fn(), cancel: vi.fn() } }))
vi.mock('../src/services/vehicleService', () => ({ vehicleService: { detail: vi.fn() } }))
const user = { id: '1', first_name: 'Client', name: 'Client Demo', phone: '+2250701020304', email: 'client@example.test', email_verified_at: '2026-01-01', role: 'customer' }
const car = { ...fixtures.vehicle.data, id: '5', slug: 'demo-car', inventory_status: 'available', sale_price: { amount_minor: '18500000', currency: 'XOF', minor_unit: 0 }, rental_daily_price: { amount_minor: '45000', currency: 'XOF', minor_unit: 0 } }
const availability = { timezone: 'Africa/Abidjan', inventory_status: 'available', from: '2026-10-01T00:00:00Z', to: '2027-10-01T00:00:00Z', intervals: [{ starts_at: '2026-10-10T00:00:00Z', ends_at: '2026-10-13T00:00:00Z' }] }
const quote = { id: '4', starts_at: '2026-10-05T00:00:00Z', ends_at: '2026-10-09T00:00:00Z', shop_timezone: 'Africa/Abidjan', billable_days: 4, daily_price_minor: '45000', total_minor: '180000', currency: 'XOF', minor_unit: 0, expires_at: '2026-10-01T12:05:00Z', conditions_version: 'demo-v1' }
const record = { id: '8', reference: 'BM-RSV-DEMO', status: 'confirmed', vehicle: { brand: 'Peugeot', model: '208', year: 2024 }, shop: { name: 'Demo', address: 'Demo' }, starts_at: '2026-10-05T00:00:00Z', ends_at: '2026-10-09T00:00:00Z', shop_timezone: 'Africa/Abidjan', total_minor: '180000', subtotal_minor: '180000', fees_minor: '0', daily_price_minor: '45000', currency: 'XOF', minor_unit: 0, created_at: '2026-10-01T12:00:00Z', is_demo: true }
const mounted = []
async function page(component, url = '/vehicles/demo-car/buy', meta = { auth: true, checkout: 'buy' }) {
 const router = createRouter({ history: createMemoryHistory(), routes: [
 { path: '/login', component: AuthPage, meta: { authMode: 'login' } },
 { path: '/register', component: AuthPage, meta: { authMode: 'register' } },
 { path: '/vehicles/:slug/buy', component: CheckoutPage, meta: { auth: true, checkout: 'buy' } },
 { path: '/vehicles/:slug/reserve', component: CheckoutPage, meta: { auth: true, checkout: 'reserve' } },
 { path: '/account/reservations/:id', component: RecordPage, meta: { kind: 'reservations' } },
 { path: '/:pathMatch(.*)*', component: { template: '<p>Destination</p>' }, meta },
 ] })
 await router.push(url); await router.isReady()
 const wrapper = mount(component, { global: { plugins: [router] } })
 mounted.push(wrapper); await flushPromises(); return { wrapper, router }
}
beforeEach(() => {
 vi.useFakeTimers({ toFake: ['Date'] }); vi.setSystemTime(new Date('2026-10-01T12:00:00Z'))
 vi.clearAllMocks(); sessionStorage.clear(); clearAuth(); auth.user = { ...user }
 favorites.items = []; favorites.ready = false
 get.mockResolvedValue({ data: [], meta: { last_page: 1 } })
 csrfCookie.mockResolvedValue(null); request.mockResolvedValue({ data: user }); post.mockResolvedValue({ data: user })
 vehicleService.detail.mockResolvedValue({ data: car })
 commerceService.availability.mockResolvedValue(availability)
 commerceService.quote.mockResolvedValue({ data: quote })
 commerceService.reserve.mockResolvedValue({ data: { id: '8' } })
 commerceService.order.mockResolvedValue({ data: { id: '9' } })
 commerceService.detail.mockResolvedValue({ data: record })
})
afterEach(() => { mounted.splice(0).forEach(x => x.unmount()); vi.useRealTimers() })
describe('Session et formulaires', () => {
 it('connecte par session CSRF sans stocker de jeton', async () => {
   clearAuth(); await login({ email: user.email, password: 'demo' })
   expect(csrfCookie).toHaveBeenCalledBefore(post)
   expect(post).toHaveBeenCalledWith('/auth/login', { email: user.email, password: 'demo' }, { quiet401: true })
   expect(auth.user.id).toBe('1'); expect(localStorage.length).toBe(0)
 })
 it('restaure la session avec auth/me', async () => {
   auth.ready = false; await restoreAuth()
   expect(request).toHaveBeenCalledWith('/auth/me', { quiet401: true }); expect(auth.user.id).toBe('1')
 })
 it('protège une route et conserve la query de retour', async () => {
   clearAuth()
   expect(await authGuard({ meta: { auth: true }, fullPath: '/account/orders?page=2' })).toEqual({ path: '/login', query: { redirect: '/account/orders?page=2' } })
 })
 it('refuse les redirections externes et conserve les liens internes', () => {
   for (const value of ['https://evil.test', '//evil.test', '/\\evil.test', '/login', '/vehicles/\nfoo']) expect(safeRedirect(value)).toBe('/account')
   expect(safeRedirect('/vehicles/demo-car?listing_type=rental')).toBe('/vehicles/demo-car?listing_type=rental')
 })
 it('le login revient à la fiche demandée', async () => {
   clearAuth()
   const { wrapper, router } = await page(AuthPage, '/login?redirect=/vehicles/demo-car')
   await wrapper.get('[name=email]').setValue(user.email); await wrapper.get('[name=password]').setValue('demo')
   await wrapper.get('form').trigger('submit'); await flushPromises()
   expect(router.currentRoute.value.path).toBe('/vehicles/demo-car')
 })
 it('affiche les erreurs de validation sans perdre les champs', async () => {
   post.mockRejectedValueOnce({ status: 422, message: 'Vérifiez les champs.', fields: { email: ['Adresse déjà utilisée.'] } })
   const { wrapper } = await page(AuthPage, '/register')
   await wrapper.get('[name=email]').setValue(user.email); await wrapper.get('form').trigger('submit'); await flushPromises()
   expect(wrapper.get('[role=alert]').text()).toContain('Adresse déjà utilisée.')
   expect(wrapper.get('[name=email]').element.value).toBe(user.email)
   expect(wrapper.find('[name=role]').exists()).toBe(false)
 })
})
describe('Favoris', () => {
 it('un visiteur est redirigé vers le login', async () => {
   clearAuth()
   const { wrapper, router } = await page({ components: { FavoriteButton }, setup: () => ({ car }), template: '<FavoriteButton :vehicle="car" />' }, '/vehicles/demo-car')
   await wrapper.get('button').trigger('click'); await flushPromises()
   expect(router.currentRoute.value.path).toBe('/login'); expect(router.currentRoute.value.query.redirect).toBe('/vehicles/demo-car')
 })
 it('ajoute puis retire le favori avec les méthodes documentées', async () => {
   request.mockResolvedValue({ data: car })
   const { wrapper } = await page({ components: { FavoriteButton }, setup: () => ({ car }), template: '<FavoriteButton :vehicle="car" />' })
   await wrapper.get('button').trigger('click'); await flushPromises()
   expect(request).toHaveBeenLastCalledWith('/me/favorites/5', { method: 'PUT' }); expect(wrapper.get('button').attributes('aria-pressed')).toBe('true')
   await wrapper.get('button').trigger('click'); await flushPromises()
   expect(request).toHaveBeenLastCalledWith('/me/favorites/5', { method: 'DELETE' }); expect(favorites.items).toHaveLength(0)
 })
 it('ne change pas le favori si le serveur refuse', async () => {
   request.mockRejectedValueOnce(new Error('Indisponible'))
   const { wrapper } = await page({ components: { FavoriteButton }, setup: () => ({ car }), template: '<FavoriteButton :vehicle="car" />' })
   await wrapper.get('button').trigger('click'); await flushPromises(); expect(favorites.items).toHaveLength(0)
 })
 it('charge toutes les pages et efface les favoris lors du changement de compte', async () => {
   get.mockResolvedValueOnce({ data: [car], meta: { last_page: 2 } }).mockResolvedValueOnce({ data: [{ ...car, id: '6' }], meta: { last_page: 2 } })
   await loadFavorites(); expect(favorites.items).toHaveLength(2)
   clearAuth(); expect(favorites.items).toHaveLength(0)
 })
})
describe('Disponibilités réelles et calendrier', () => {
 it('bloque intersections, périodes traversantes et inventaire indisponible', () => {
   for (const [a,b] of [['2026-10-09','2026-10-11'], ['2026-10-11','2026-10-12'], ['2026-10-09','2026-10-14'], ['2026-10-12','2026-10-14']]) expect(rangeAvailable(a,b,availability)).toBe(false)
   expect(rangeAvailable('2026-10-05','2026-10-09',{ ...availability, inventory_status: 'sold' })).toBe(false)
 })
 it('autorise les périodes adjacentes et ignore les retenues expirées', () => {
   expect(rangeAvailable('2026-10-05','2026-10-10',availability)).toBe(true)
   expect(rangeAvailable('2026-10-13','2026-10-15',availability)).toBe(true)
   expect(rangeAvailable('2026-10-10','2026-10-12',{ ...availability, intervals: availability.intervals.map(x => ({ ...x, expires_at: '2026-10-01T11:00:00Z' })) })).toBe(true)
 })
 it('respecte le fuseau de la boutique y compris le changement d’heure', () => {
   expect(new Date(zonedMidnight('2026-10-25', 'Europe/Paris')).toISOString()).toBe('2026-10-24T22:00:00.000Z')
   expect(new Date(zonedMidnight('2026-10-26', 'Europe/Paris')).toISOString()).toBe('2026-10-25T23:00:00.000Z')
 })
 it('désactive les jours indisponibles et permet un retour adjacent', async () => {
   const wrapper = mount(DateCalendar, { props: { availability, start: '', end: '' } }); mounted.push(wrapper)
   expect(wrapper.get('[data-date="2026-10-10"]').attributes('disabled')).toBeDefined()
   await wrapper.get('[data-date="2026-10-05"]').trigger('click')
   await wrapper.setProps({ start: '2026-10-05' })
   expect(wrapper.get('[data-date="2026-10-10"]').attributes('disabled')).toBeUndefined()
   expect(wrapper.get('[data-date="2026-10-11"]').attributes('disabled')).toBeDefined()
 })
})
async function summary(wrapper, rental = false) {
 if (!rental) await wrapper.get('input[type="datetime-local"]').setValue('2026-10-05T10:00')
 if (rental) {
   const calendar = wrapper.getComponent(DateCalendar)
   calendar.vm.$emit('update:start', '2026-10-05'); calendar.vm.$emit('update:end', '2026-10-09'); await nextTick()
 }
 await wrapper.findAll('button').find(x => x.text() === 'Voir le résumé').trigger('click'); await flushPromises()
 await wrapper.findAll('button').find(x => x.text() === 'Choisir le paiement démo').trigger('click'); await flushPromises()
}
describe('Réservation et achat', () => {
 it('envoie le devis serveur, affiche sa valeur et redirige vers la confirmation', async () => {
   const { wrapper, router } = await page(CheckoutPage, '/vehicles/demo-car/reserve')
   await summary(wrapper, true)
   expect(wrapper.text()).toContain('180'); expect(wrapper.find('input[name=card]').exists()).toBe(false)
   await wrapper.findAll('button').find(x => x.text() === 'Confirmer la réservation').trigger('click'); await flushPromises()
   expect(commerceService.reserve).toHaveBeenCalledWith({ quote_id: '4', payment_method: 'MOBILE_MONEY_DEMO', conditions_version: 'demo-v1' }, expect.any(String))
   expect(router.currentRoute.value.path).toBe('/reservation-confirmation/8')
 })
 it('enregistre un achat sans montant faisant autorité', async () => {
   const { wrapper, router } = await page(CheckoutPage)
   await summary(wrapper)
   await wrapper.findAll('button').find(x => x.text() === 'Confirmer l’achat').trigger('click'); await flushPromises()
   expect(commerceService.order).toHaveBeenCalledWith({ vehicle_id: '5', payment_method: 'MOBILE_MONEY_DEMO', expected_price_minor: '18500000', currency: 'XOF', handover: expect.objectContaining({ mode: 'self', scheduled_local: '2026-10-05T10:00', contact_phone: '+2250701020304' }) }, expect.any(String))
   expect(router.currentRoute.value.path).toBe('/order-confirmation/9')
 })
 it('empêche le double clic et rejoue la même clé après un résultat réseau incertain', async () => {
   let reject
   commerceService.order.mockImplementationOnce(() => new Promise((_, no) => { reject = no }))
   const { wrapper } = await page(CheckoutPage); await summary(wrapper)
   const button = wrapper.findAll('button').find(x => x.text() === 'Confirmer l’achat')
   await button.trigger('click'); await button.trigger('click')
   expect(commerceService.order).toHaveBeenCalledTimes(1)
   const key = commerceService.order.mock.calls[0][1]
   reject(new Error('Réseau')); await flushPromises()
   expect(wrapper.find('fieldset').attributes('disabled')).toBeDefined()
   await wrapper.findAll('button').find(x => x.text() === 'Vérifier ma demande').trigger('click'); await flushPromises()
   expect(commerceService.order.mock.calls[1][1]).toBe(key)
 })
 it('recharge le calendrier après un conflit de disponibilité', async () => {
   commerceService.reserve.mockRejectedValueOnce({ status: 409, code: 'VEHICLE_UNAVAILABLE', message: 'Conflit' })
   const { wrapper } = await page(CheckoutPage, '/vehicles/demo-car/reserve'); await summary(wrapper,true)
   await wrapper.findAll('button').find(x => x.text() === 'Confirmer la réservation').trigger('click'); await flushPromises()
   expect(wrapper.text()).toContain('Ces dates viennent de devenir indisponibles.')
   expect(commerceService.availability).toHaveBeenCalledTimes(4)
   expect(wrapper.findComponent(DateCalendar).exists()).toBe(true)
 })
 it('demande un nouveau devis après expiration', async () => {
   const { wrapper } = await page(CheckoutPage, '/vehicles/demo-car/reserve'); await summary(wrapper,true)
   vi.setSystemTime(new Date('2026-10-01T12:06:00Z'))
   await wrapper.findAll('button').find(x => x.text() === 'Confirmer la réservation').trigger('click'); await flushPromises()
   expect(commerceService.reserve).not.toHaveBeenCalled()
   expect(wrapper.text()).toContain('Le devis a expiré')
 })
 it('désactive un achat SOLD', async () => {
   const { wrapper } = await page({ components: { TransactionActions }, setup: () => ({ car: { ...car, inventory_status: 'sold' } }), template: '<TransactionActions :vehicle="car" type="buy" />' })
   expect(wrapper.get('button').attributes('disabled')).toBeDefined(); expect(wrapper.text()).toBe('Vendu')
 })
 it('bloque les transactions sans e-mail vérifié', async () => {
   auth.user.email_verified_at = null; auth.user.email_verification_required = true
   const { wrapper } = await page(CheckoutPage)
   expect(wrapper.findAll('button').find(x => x.text() === 'Voir le résumé').attributes('disabled')).toBeDefined()
 })
 it('annule seulement après confirmation et résultat serveur', async () => {
   commerceService.cancel.mockResolvedValue({ data: { ...record, status: 'cancelled' } })
   const { wrapper } = await page(RecordPage, '/account/reservations/8')
   await wrapper.findAll('button').find(x => x.text() === 'Annuler cette réservation').trigger('click')
   expect(commerceService.cancel).not.toHaveBeenCalled()
   expect(wrapper.get('dialog').attributes('open')).toBe('')
   await wrapper.findAll('button').find(x => x.text() === 'Confirmer l’annulation').trigger('click'); await flushPromises()
   expect(commerceService.cancel).toHaveBeenCalledWith('8'); expect(wrapper.text()).toContain('Annulée')
 })
 it('cache l’annulation après départ ou pour un statut final', () => {
   for(const status of ['active','completed','cancelled','expired','rejected']) expect(canCancelReservation({ ...record,status })).toBe(false)
   expect(canCancelReservation({ ...record, starts_at: '2026-09-01T00:00:00Z' })).toBe(false)
 })
})


describe('Remise et prix négocié', () => {
 it('exige un rendez-vous avant de passer au paiement', async () => {
  const {wrapper}=await page(CheckoutPage)
  await wrapper.findAll('button').find(x=>x.text()==='Voir le résumé').trigger('click')
  await flushPromises()
  expect(wrapper.text()).toContain('Renseignez la date')
  expect(commerceService.order).not.toHaveBeenCalled()
 })
 it('exige une adresse pour la livraison et transmet les coordonnées du tiers', async () => {
  const {wrapper}=await page(CheckoutPage)
  await wrapper.get('input[type="datetime-local"]').setValue('2026-10-05T10:00')
  await wrapper.get('.handover-form select').setValue('delivery')
  await wrapper.findAll('button').find(x=>x.text()==='Voir le résumé').trigger('click')
  await flushPromises()
  expect(wrapper.text()).toContain('Renseignez la ville')
  await wrapper.get('.handover-form select').setValue('proxy')
  await wrapper.get('.handover-form input[maxlength="120"]').setValue('Mandataire QA')
  await wrapper.get('input[type="tel"]').setValue('+2250501020304')
  await summary(wrapper)
  await wrapper.findAll('button').find(x=>x.text()==='Confirmer l’achat').trigger('click')
  await flushPromises()
  expect(commerceService.order.mock.calls[0][0].handover).toMatchObject({mode:'proxy',contact_name:'Mandataire QA',contact_phone:'+2250501020304',address:'',latitude:null})
 })
 it('applique seulement le prix de la proposition acceptée obtenue du serveur', async () => {
  vehicleService.detail.mockResolvedValueOnce({data:{...car,negotiation_enabled:true}})
  get.mockResolvedValueOnce({data:{id:'12',vehicle_id:'5',status:'accepted',amount_minor:'17000000',currency:'XOF',minor_unit:0,expires_at:'2026-10-02T12:00:00Z'}})
  const {wrapper}=await page(CheckoutPage,'/vehicles/demo-car/buy?offer=12')
  await summary(wrapper)
  await wrapper.findAll('button').find(x=>x.text()==='Confirmer l’achat').trigger('click')
  await flushPromises()
  expect(commerceService.order.mock.calls[0][0]).toMatchObject({price_offer_id:'12',expected_price_minor:'17000000'})
 })
 it('bloque une proposition expirée sans envoyer une commande au prix public', async () => {
  get.mockResolvedValueOnce({data:{id:'12',vehicle_id:'5',status:'expired',expires_at:'2026-09-30T12:00:00Z'}})
  const {wrapper}=await page(CheckoutPage,'/vehicles/demo-car/buy?offer=12')
  expect(wrapper.text()).toContain('Cette proposition n’est plus utilisable')
  expect(wrapper.findAll('button').find(x=>x.text()==='Voir le résumé').attributes('disabled')).toBeDefined()
  expect(commerceService.order).not.toHaveBeenCalled()
 })
})
