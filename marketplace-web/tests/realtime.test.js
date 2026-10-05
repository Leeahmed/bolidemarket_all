import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createRealtime } from '../../web-shared/realtime'
import { vehicleService } from '../src/services/vehicleService'
import { commerceService } from '../src/services/commerceService'
import CatalogPage from '../src/pages/CatalogPage.vue'
import VehiclePage from '../src/pages/VehiclePage.vue'
import RecordPage from '../src/pages/RecordPage.vue'
import fixtures from './fixtures.json'
const bus = vi.hoisted(() => new Map())
vi.mock('../src/services/realtime',()=>({realtime:{on:(scope,cb)=>{if(!bus.has(scope))bus.set(scope,new Set());bus.get(scope).add(cb);return()=>bus.get(scope).delete(cb)}}}))
vi.mock('../src/services/vehicleService',()=>({vehicleService:{list:vi.fn(),detail:vi.fn()}}))
vi.mock('../src/services/commerceService',()=>({commerceService:{detail:vi.fn()}}))
vi.mock('../src/composables/useReferences',()=>({useReferences:()=>({references:{countries:[],cities:[],districts:[],brands:[],models:[],categories:[],currencies:[],features:[]}})}))
let wrappers=[]
const emit = async (scope,type,data={}) => { for(const cb of bus.get(scope)||[]) cb({type,data}); await vi.advanceTimersByTimeAsync(150); await flushPromises() }
async function page(component,url='/vehicles') {
 const router=createRouter({history:createMemoryHistory(),routes:[{path:'/vehicles',component:CatalogPage},{path:'/vehicles/:slug',component:VehiclePage},{path:'/account/reservations/:id',component:RecordPage,meta:{kind:'reservations'}},{path:'/:pathMatch(.*)*',component:{template:'<div/>'}}]})
 await router.push(url);await router.isReady()
 const w=mount(component,{global:{plugins:[router]}});wrappers.push(w);await flushPromises();return w
}
beforeEach(()=>{vi.useFakeTimers();bus.clear();vehicleService.list.mockResolvedValue(structuredClone(fixtures.vehicles));vehicleService.detail.mockResolvedValue(structuredClone(fixtures.vehicle))})
afterEach(()=>{wrappers.forEach(w=>w.unmount());wrappers=[];vi.useRealTimers();vi.clearAllMocks()})
it('patches only the visible card after a price change',async()=>{
 const w=await page(CatalogPage),v=fixtures.vehicles.data[0]
 vehicleService.detail.mockResolvedValue({data:{...v,sale_price:{amount_minor:'19999000',currency:'XOF',minor_unit:0}}})
 await emit('public','VehicleUpdated',{vehicle_id:v.id,slug:v.slug})
 expect(vehicleService.list).toHaveBeenCalledTimes(1)
 expect(w.text().replace(/\s/g,'')).toContain('19999000')
})
it('announces new publications without inserting or reordering cards',async()=>{
 const w=await page(CatalogPage)
 await emit('public','VehiclePublished',{vehicle_id:'999'})
 expect(w.text()).toContain('De nouvelles annonces sont disponibles.')
 expect(vehicleService.list).toHaveBeenCalledTimes(1)
 expect(w.findAll('.vehicle-card')).toHaveLength(fixtures.vehicles.data.length)
})
it('removes unpublished cards and unsubscribes on route unmount',async()=>{
 const w=await page(CatalogPage),v=fixtures.vehicles.data[0]
 await emit('public','VehicleUnpublished',{vehicle_id:v.id})
 expect(w.findAll('.vehicle-card')).toHaveLength(fixtures.vehicles.data.length-1)
 w.unmount();expect(bus.get('public').size).toBe(0)
})
it('refreshes sold details and disables the purchase action',async()=>{
 const v=fixtures.vehicle.data,w=await page(VehiclePage,'/vehicles/'+v.slug)
 vehicleService.detail.mockResolvedValue({data:{...v,inventory_status:'sold'}})
 await emit('public','VehicleStatusChanged',{vehicle_id:v.id,slug:v.slug})
 expect(w.text()).toContain('Ce véhicule vient d’être vendu.')
 expect(w.find('button[disabled]').exists()).toBe(true)
})
it('handles withdrawn details without an exception',async()=>{
 const v=fixtures.vehicle.data,w=await page(VehiclePage,'/vehicles/'+v.slug)
 vehicleService.detail.mockRejectedValue(Object.assign(new Error('Missing'),{status:404}))
 await emit('public','VehicleUnpublished',{vehicle_id:v.id,slug:v.slug})
 expect(w.text()).toContain('Cette annonce n’est plus disponible.')
 expect(w.find('.detail-summary').exists()).toBe(false)
})
it('updates a reservation from its private confirmation event',async()=>{
 const r={id:'5',reference:'QA',status:'pending',vehicle:{brand:'Toyota',model:'RAV4'},shop:{name:'QA'},total_minor:'45000',currency:'XOF',minor_unit:0}
 commerceService.detail.mockResolvedValue({data:r})
 const w=await page(RecordPage,'/account/reservations/5')
 commerceService.detail.mockResolvedValue({data:{...r,status:'confirmed'}})
 await emit('private','ReservationConfirmed',{id:'5'})
 expect(w.text()).toContain('Confirmée')
})
it('deduplicates transport events, reconciles on reconnect and disconnects old identities',async()=>{
 const channels=new Map(),instances=[]
 class FakeEcho {
  constructor(options){this.options=options;this.disconnected=false;instances.push(this)}
  channel(n){return this.private(n)}
  private(n){const c={handlers:{},listen(t,cb){this.handlers[t]=cb;return this},subscribed(cb){this.ready=cb;return this}};channels.set(n,c);return c}
  disconnect(){this.disconnected=true}
 }
 const rt=createRealtime({Echo:FakeEcho,Pusher:{},post:vi.fn(),env:{VITE_REVERB_APP_KEY:'test'}}),listener=vi.fn()
 const off=rt.on('private',listener);rt.connect({userId:'1'})
 const c=channels.get('user.1'),e={type:'ReservationConfirmed',event_id:'unique',data:{id:'1'}}
 c.handlers['.ReservationConfirmed'](e);c.handlers['.ReservationConfirmed'](e);await flushPromises()
 expect(listener).toHaveBeenCalledTimes(1)
 c.ready();await flushPromises();expect(listener).toHaveBeenLastCalledWith({type:'Reconnected',data:{}})
 rt.connect({userId:'2'});expect(instances[0].disconnected).toBe(true)
 c.handlers['.ReservationConfirmed']({...e,event_id:'late'});await flushPromises();expect(listener).toHaveBeenCalledTimes(2)
 off();rt.disconnect();expect(instances[1].disconnected).toBe(true)
})
