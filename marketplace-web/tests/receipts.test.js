import { mount,flushPromises } from '@vue/test-utils'
import { describe,it,expect,vi } from 'vitest'
import ReceiptsBrowser from '../../web-shared/ReceiptsBrowser.vue'
import { receiptMoney } from '../../web-shared/receiptFormat'
const receipt={reference:'BM-RCP-2026-DEMO',type:'sale',currency:'XOF',minor_unit:0,subtotal_minor:'18500000',fees_minor:'0',total_minor:'18500000',payment_method:'CARD_DEMO',payment_status:'paid',issued_at:'2026-10-05T10:00:00Z',is_demo:true,buyer:{name:'Client Démo'},seller:{name:'Boutique historique',address:'Cocody'},vehicle:{brand:'Toyota',model:'RAV4',reference:'BM-V-DEMO',year:2024},transaction:{order_reference:'BM-ORD-DEMO'},demo_notice:'Aucun paiement réel n’a été effectué.'}
const scope="me"
function create(get,extra={}){return mount(ReceiptsBrowser,{props:{get,scope,apiBase:'http://127.0.0.1:8000/api/v1',detailBase:scope==='me'?'/account/receipts':'/receipts',...extra},global:{stubs:{RouterLink:{props:['to'],template:'<a :href="to"><slot/></a>'}}}})}
describe('Reçus privés',()=>{
 it('liste les snapshots et pointe vers les PDF backend privés',async()=>{
  const get=vi.fn().mockResolvedValue({data:[receipt],meta:{last_page:1}})
  const w=create(get,{shopId:scope==='merchant'?'13':undefined});await flushPromises()
  expect(get.mock.calls[0][0]).toBe('/'+scope+'/receipts')
  if(scope==='merchant')expect(get.mock.calls[0][1].shop_id).toBe('13')
  expect(w.text()).toContain('Boutique historique');expect(w.text()).toContain('18 500 000 FCFA')
  const a=w.findAll('a'),pdf=a.find(x=>x.text()==='Télécharger le PDF'),print=a.find(x=>x.text()==='Imprimer')
  expect(pdf.attributes('href')).toBe('http://127.0.0.1:8000/api/v1/'+scope+'/receipts/'+receipt.reference+'/pdf')
  expect(print.attributes('href')).toContain('?disposition=inline');expect(print.attributes('target')).toBe('_blank')
  w.unmount()
 })
 it('affiche le document HTML complet sans iframe ni édition',async()=>{
  const w=create(vi.fn().mockResolvedValue({data:receipt}),{reference:receipt.reference});await flushPromises()
  expect(w.get('article[aria-label="Reçu BolideMarket"]').text()).toContain('REÇU DE DÉMONSTRATION')
  expect(w.text()).toContain('Client Démo');expect(w.text()).toContain('BM-ORD-DEMO')
  expect(w.find('iframe').exists()).toBe(false);expect(w.find('input').exists()).toBe(false)
  w.unmount()
 })
 it('présente un refus d’accès sans exposer le document',async()=>{
  const w=create(vi.fn().mockRejectedValue({status:404,message:'Cette ressource est introuvable.'}),{reference:'PRIVATE'})
  await flushPromises();expect(w.get('[role=alert]').text()).toContain('introuvable')
  expect(w.find('.receipt-document').exists()).toBe(false);expect(w.find('a[download]').exists()).toBe(false);w.unmount()
 })
 it('présente l’état vide et les montants EUR USD CAD sans conversion',async()=>{
  const w=create(vi.fn().mockResolvedValue({data:[],meta:{last_page:1}}));await flushPromises()
  expect(w.text()).toContain('Aucun reçu pour le moment')
  for(const [currency,symbol] of [['EUR','€'],['USD','$'],['CAD','CA$']])expect(receiptMoney({...receipt,currency,minor_unit:2,total_minor:'1234567'})).toBe('12 345,67 '+symbol)
  w.unmount()
 })
 it('décrit les dates et le tarif historique de location',async()=>{
  const w=create(vi.fn().mockResolvedValue({data:{...receipt,type:'rental',total_minor:'180000',subtotal_minor:'180000',transaction:{reservation_reference:'BM-RSV-DEMO',starts_at:'2026-10-05T00:00:00Z',ends_at:'2026-10-09T00:00:00Z',timezone:'Africa/Abidjan',days:4,daily_price_minor:'45000'}}}),{reference:receipt.reference});await flushPromises()
  expect(w.text()).toContain('4 jour(s)');expect(w.text()).toContain('45 000 FCFA / jour');expect(w.text()).toContain('180 000 FCFA');w.unmount()
 })
})
