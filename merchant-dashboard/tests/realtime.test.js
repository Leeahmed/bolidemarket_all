import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { mount,flushPromises } from '@vue/test-utils'
import { createRouter,createMemoryHistory } from 'vue-router'
import { shopState } from '../src/stores/shop'
import { merchantDashboardService as api } from '../src/services/merchant'
import DashboardPage from '../src/pages/DashboardPage.vue'
const bus=vi.hoisted(()=>new Set())
vi.mock('../src/services/realtime',()=>({realtime:{on:(_,cb)=>{bus.add(cb);return()=>bus.delete(cb)}}}))
vi.mock('../src/services/merchant',()=>({merchantDashboardService:{get:vi.fn()}}))
let wrapper
const state={vehicles_total:1,fleet:{available:1,rented:0,sold:0,other:0},pending_reservations:0,activity:[],recent_vehicles:[],recent_reservations:[],recent_orders:[],as_of:'2026-10-04'}
async function start(){const router=createRouter({history:createMemoryHistory(),routes:[{path:'/:pathMatch(.*)*',component:DashboardPage}]});await router.push('/dashboard');await router.isReady();wrapper=mount(DashboardPage,{global:{plugins:[router]}});await flushPromises()}
async function emit(event){for(const cb of bus)cb(event);await vi.advanceTimersByTimeAsync(150);await flushPromises()}
beforeEach(()=>{vi.useFakeTimers();bus.clear();shopState.shops=[{id:'7',slug:'qa',name:'QA'}];shopState.id='7';api.get.mockResolvedValue({data:structuredClone(state)})})
afterEach(()=>{wrapper?.unmount();vi.useRealTimers();vi.clearAllMocks()})
it('refreshes dashboard counters and recent reservations without reload',async()=>{await start();api.get.mockResolvedValue({data:{...state,pending_reservations:1,recent_reservations:[{id:'9',vehicle:{title:'RAV4 QA'},status:'pending'}]}});await emit({type:'ReservationCreated',data:{shop_id:'7',id:'9'}});expect(wrapper.text()).toContain('RAV4 QA');expect(wrapper.findAll('.kpi strong')[1].text()).toBe('1')})
it('ignores another shop and removes listeners on unmount',async()=>{await start();await emit({type:'ReservationCreated',data:{shop_id:'8'}});expect(api.get).toHaveBeenCalledTimes(1);wrapper.unmount();expect(bus.size).toBe(0)})
it('reconciles REST after reconnection',async()=>{await start();await emit({type:'Reconnected',data:{}});expect(api.get).toHaveBeenCalledTimes(2)})
