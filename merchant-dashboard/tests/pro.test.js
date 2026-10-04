import { beforeEach, afterEach, describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { reactive, ref } from 'vue'
import { auth, merchantGuard, logout, safeDestination } from '../src/stores/auth'
import { currentShop, shopState } from '../src/stores/shop'
import { vehiclePayload } from '../src/utils/pro'
import { request,post } from '../src/services/api'
import { merchantDashboardService as dashboard,merchantVehicleService as vehicles,merchantReservationService as reservations,merchantOrderService as orders,merchantShopService as shops } from '../src/services/merchant'
import DashboardPage from '../src/pages/DashboardPage.vue'
import VehiclesPage from '../src/pages/VehiclesPage.vue'
import VehicleFormPage from '../src/pages/VehicleFormPage.vue'
import VehicleActions from '../src/components/VehicleActions.vue'
import CommercePage from '../src/pages/CommercePage.vue'
import CommerceDetailPage from '../src/pages/CommerceDetailPage.vue'
import ShopPage from '../src/pages/ShopPage.vue'
import PhotoManager from '../src/components/PhotoManager.vue'
vi.mock('../src/services/api',()=>({request:vi.fn(),post:vi.fn(),csrfCookie:vi.fn(),get:vi.fn()}))
vi.mock('../src/services/merchant',()=>({
 merchantDashboardService:{get:vi.fn(),clients:vi.fn()},
 merchantVehicleService:{list:vi.fn(),get:vi.fn(),create:vi.fn(),update:vi.fn(),status:vi.fn(),upload:vi.fn(),remove:vi.fn(),primary:vi.fn(),removeImage:vi.fn()},
 merchantReservationService:{list:vi.fn(),get:vi.fn(),action:vi.fn()},
 merchantOrderService:{list:vi.fn(),get:vi.fn(),action:vi.fn()},
 merchantShopService:{list:vi.fn(),update:vi.fn(),media:vi.fn()},
}))
vi.mock('../src/composables/useReferences',()=>({useReferences:()=>({references:reactive({countries:[{code:'CI',name:'Côte d’Ivoire',currency_code:'XOF'}],cities:[{id:'1',country_code:'CI',name:'Abidjan'}],districts:[],brands:[{id:'1',name:'Toyota'}],models:[{id:'1',name:'RAV4',brand_id:'1'}],categories:[{id:'1',label:'SUV'}],features:[]}),state:ref('success'),error:ref(null),load:vi.fn()})}))
const shop={id:'1',name:'Boutique QA',slug:'qa',currency:{code:'XOF',minor_unit:0},location:{country:{code:'CI',name:'Côte d’Ivoire'},city:{id:'1',name:'Abidjan'}},address:'Abidjan',timezone:'Africa/Abidjan',status:'published',is_demo:true}
const vehicle={id:'7',title:'Toyota RAV4',reference:'QA-7',brand:{id:'1'},model:{id:'1',name:'RAV4'},category:{id:'1'},year:2024,condition:'used',description:'Démo',fuel:'petrol',transmission:'automatic',offer_types:['sale'],sale_price:{amount_minor:'18500000',currency:'XOF',minor_unit:0},inventory_status:'available',publication_status:'draft',images:[],features:[],location:shop.location,shop}
const record={id:'9',reference:'RES-9',kind:'sale',status:'pending',vehicle:{title:'Toyota RAV4'},customer:{name:'Client QA',email:'qa@example.test'},total_minor:'45000',currency:'XOF',minor_unit:0,created_at:'2026-10-01',starts_at:'2026-10-10',ends_at:'2026-10-12',billable_days:2,shop:{name:shop.name}}
const meta={total:1,current_page:1,last_page:1}
let wrappers=[]
async function page(component,path='/dashboard',routePath=path){
 const router=createRouter({history:createMemoryHistory(),routes:[{path:routePath,component},{path:'/:pathMatch(.*)*',component:{template:'<div/>'}}]})
 await router.push(path);await router.isReady()
 const wrapper=mount(component,{global:{plugins:[router]}});wrappers.push(wrapper);await flushPromises();return {wrapper,router}
}
beforeEach(()=>{
 vi.clearAllMocks();auth.user={id:'1',role:'merchant'};auth.ready=true;auth.error=null
 shopState.shops=[structuredClone(shop)];shopState.id='1';shopState.ready=true
 vehicles.list.mockResolvedValue({data:[vehicle],meta});vehicles.get.mockResolvedValue({data:vehicle});vehicles.create.mockResolvedValue({data:vehicle});vehicles.update.mockResolvedValue({data:vehicle});vehicles.status.mockResolvedValue({data:vehicle})
 reservations.list.mockResolvedValue({data:[record],meta});orders.list.mockResolvedValue({data:[record],meta});reservations.get.mockResolvedValue({data:record});orders.get.mockResolvedValue({data:record});reservations.action.mockResolvedValue({data:{...record,status:'confirmed'}})
 shops.update.mockResolvedValue({data:shop});post.mockResolvedValue(null)
 dashboard.get.mockResolvedValue({data:{vehicles_total:1,fleet:{available:1,rented:0,sold:0,other:0},pending_reservations:0,activity:Array.from({length:6},(_,i)=>({month:'2026-0'+(i+1),sales:0})),recent_vehicles:[vehicle],recent_reservations:[],recent_orders:[],as_of:'2026-10-01'}})
})
afterEach(()=>{wrappers.forEach(w=>w.unmount());wrappers=[]})
describe('merchant authentication',()=>{
 it('redirects anonymous requests to login retaining the route',async()=>{auth.user=null;expect(await merchantGuard({path:'/vehicles',fullPath:'/vehicles?q=toy',query:{}})).toEqual({path:'/login',query:{redirect:'/vehicles?q=toy'}})})
 it('rejects customer accounts',async()=>{auth.user={role:'customer'};expect(await merchantGuard({path:'/dashboard',query:{}})).toEqual({path:'/login',query:{forbidden:'1'}})})
 it('restores the server session before granting access',async()=>{auth.ready=false;request.mockResolvedValue({data:{id:'1',role:'merchant'}});expect(await merchantGuard({path:'/dashboard',query:{}})).toBeUndefined();expect(request).toHaveBeenCalledWith('/auth/me',{quiet401:true})})
 it('sends merchants from login to dashboard',async()=>{expect(await merchantGuard({path:'/login',query:{}})).toBe('/dashboard')})
 it('rejects external redirect targets',()=>{for(const target of ['//evil.test','https://evil.test','/unknown','/vehicles\\evil'])expect(safeDestination(target)).toBe('/dashboard')})
 it('logs out server-side then clears the session',async()=>{await logout();expect(post).toHaveBeenCalledWith('/auth/logout');expect(auth.user).toBeNull()})
 it('retains the session if logout fails',async()=>{post.mockRejectedValue(new Error('Réseau'));await expect(logout()).rejects.toThrow('Réseau');expect(auth.user.role).toBe('merchant')})
})
describe('vehicle money contract',()=>{
 const f={offer:'sale',sale:'18500000',rent:'',vehicle_model_id:'1',category_id:'1',brand_id:'1',district_id:''}
 it('infers currency via shop without sending a client-controlled currency',()=>{const result=vehiclePayload(f,shop,false);expect(result.sale_price_minor).toBe('18500000');expect(result.currency_code).toBeUndefined();expect(result.shop_id).toBe('1')})
 it('converts euro prices exactly to minor units',()=>{expect(vehiclePayload({...f,sale:'12345,67'},{...shop,currency:{code:'EUR',minor_unit:2}},false).sale_price_minor).toBe('1234567')})
 it('rejects missing sale price',()=>expect(()=>vehiclePayload({...f,sale:''},shop,false)).toThrow('vente'))
 it('rejects missing rental tariff',()=>expect(()=>vehiclePayload({...f,offer:'rental'},shop,false)).toThrow('location'))
 it('supports both offers and removes shop from edit payload',()=>{const result=vehiclePayload({...f,offer:'both',rent:'45000'},shop,true);expect(result.is_for_sale&&result.is_for_rent).toBe(true);expect(result.rent_daily_minor).toBe('45000');expect(result.shop_id).toBeUndefined()})
})
describe('connected Pro pages',()=>{
 it('loads dashboard from active shop and renders server fleet',async()=>{const {wrapper}=await page(DashboardPage);expect(dashboard.get).toHaveBeenCalledWith('1');expect(wrapper.text()).toContain('Toyota RAV4');expect(wrapper.findAll('.kpi')[0].text()).toContain('1');expect(wrapper.find('a[href*="/shops/qa"]').exists()).toBe(true)})
 it('offers retry on API failure without showing fake figures',async()=>{dashboard.get.mockRejectedValueOnce(new Error('Service indisponible'));const {wrapper}=await page(DashboardPage);expect(wrapper.text()).toContain('Réessayer');expect(wrapper.find('.kpi').exists()).toBe(false)})
 it('filters vehicles server-side',async()=>{const {wrapper}=await page(VehiclesPage,'/vehicles');await wrapper.find('input[type=search]').setValue('Toyota');await wrapper.find('form').trigger('submit');await flushPromises();expect(vehicles.list).toHaveBeenLastCalledWith(expect.objectContaining({q:'Toyota',shop_id:'1',page:1}))})
 it('creates and publishes a vehicle using the same saved record',async()=>{const {wrapper}=await page(VehicleFormPage,'/vehicles/create');await wrapper.findAll('select')[0].setValue('1');await flushPromises();await wrapper.findAll('select')[1].setValue('1');await wrapper.findAll('select')[3].setValue('1');await wrapper.findAll('.steps button')[1].trigger('click');await wrapper.find('input[inputmode=decimal]').setValue('18500000');await wrapper.findAll('.steps button')[2].trigger('click');await wrapper.find('textarea').setValue('Véhicule de démonstration');await wrapper.findAll('.steps button')[4].trigger('click');await wrapper.findAll('.form-footer button').find(b=>b.text()==='Publier').trigger('click');await flushPromises();expect(vehicles.create).toHaveBeenCalledWith(expect.objectContaining({sale_price_minor:'18500000',shop_id:'1'}));expect(vehicles.status).toHaveBeenCalledWith('7',{publication_status:'published'})})
 it('pre-fills and edits without creating another vehicle',async()=>{const {wrapper}=await page(VehicleFormPage,'/vehicles/7/edit','/vehicles/:id/edit');expect(wrapper.find('input[type=number]').element.value).toBe('2024');await wrapper.findAll('.steps button')[1].trigger('click');await wrapper.find('input[inputmode=decimal]').setValue('19000000');await wrapper.findAll('.steps button')[4].trigger('click');await wrapper.findAll('.form-footer button').find(b=>b.text().includes('brouillon')).trigger('click');await flushPromises();expect(vehicles.update).toHaveBeenCalledWith('7',expect.objectContaining({sale_price_minor:'19000000'}));expect(vehicles.create).not.toHaveBeenCalled()})
 it('requires confirmation for a status update',async()=>{const {wrapper}=await page({components:{VehicleActions},template:'<VehicleActions :vehicle="vehicle"/>',data:()=>({vehicle})},'/vehicles');await wrapper.findAll('button').find(b=>b.text()==='Disponibilité').trigger('click');expect(vehicles.status).not.toHaveBeenCalled();await wrapper.find('select').setValue('other');await wrapper.findAll('dialog button').find(b=>b.text()==='Confirmer').trigger('click');await flushPromises();expect(vehicles.status).toHaveBeenCalledWith('7',{inventory_status:'other'})})
 it('loads reservations for selected shop',async()=>{const {wrapper}=await page(CommercePage,'/reservations');expect(reservations.list).toHaveBeenCalledWith(expect.objectContaining({shop_id:'1'}));expect(wrapper.text()).toContain('Client QA')})
 it('loads only sales for orders',async()=>{await page(CommercePage,'/orders');expect(orders.list).toHaveBeenCalledWith(expect.objectContaining({kind:'sale'}))})
 it('reuses reservations for active and completed rentals',async()=>{await page(CommercePage,'/rentals');expect(reservations.list).toHaveBeenCalledWith(expect.objectContaining({rentals:1}));expect(orders.list).not.toHaveBeenCalled()})
 it('confirms a reservation via the existing API after modal approval',async()=>{const {wrapper}=await page(CommerceDetailPage,'/reservations/9','/reservations/:id');await wrapper.findAll('.panel .actions button').find(b=>b.text()==='Confirmer').trigger('click');expect(reservations.action).not.toHaveBeenCalled();await wrapper.findAll('dialog button').find(b=>b.text()==='Confirmer').trigger('click');await flushPromises();expect(reservations.action).toHaveBeenCalledWith('9','confirm')})
 it('updates shop settings and retains public slug',async()=>{const {wrapper}=await page(ShopPage,'/shop');await wrapper.find('input').setValue('Boutique modifiée');await wrapper.find('form').trigger('submit');await flushPromises();expect(shops.update).toHaveBeenCalledWith('1',expect.objectContaining({name:'Boutique modifiée'}));expect(wrapper.find('a[href*="/shops/qa"]').exists()).toBe(true);expect(currentShop.value.id).toBe('1')})
 it('rejects invalid photo MIME before upload',async()=>{const {wrapper}=await page({components:{PhotoManager},template:'<PhotoManager v-model="photos"/>',data:()=>({photos:[]})},'/vehicles/create');const input=wrapper.find('input[type=file]');Object.defineProperty(input.element,'files',{value:[new File(['bad'],'bad.svg',{type:'image/svg+xml'})]});await input.trigger('change');expect(wrapper.text()).toContain('5 Mo maximum');expect(vehicles.upload).not.toHaveBeenCalled()})
})
