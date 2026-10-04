import { beforeEach, afterEach, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { reactive, ref } from 'vue'
import CountryPhone from '../src/components/CountryPhone.vue'
import AccountLayout from '../src/components/AccountLayout.vue'
import ProfilePage from '../src/pages/ProfilePage.vue'
import AuthPage from '../src/pages/AuthPage.vue'
import ProRegisterPage from '../src/pages/ProRegisterPage.vue'
import UserAvatar from '../src/components/UserAvatar.vue'
import { auth, clearAuth, loginDestination, authGuard } from '../src/stores/auth'
import { appConfig } from '../src/stores/appConfig'
import { post, request, get } from '../src/services/api'
import { formatMoney } from '../src/utils/format'
vi.mock('../src/services/api', () => ({ post: vi.fn(), request: vi.fn(), get: vi.fn(), csrfCookie: vi.fn() }))
vi.mock('../src/composables/useReferences', () => ({ useReferences: () => ({ references: reactive({ countries: [{code:'CI',name:'Côte d’Ivoire',phone_code:'+225',currency_code:'XOF',currency_symbol:'FCFA'},{code:'FR',name:'France',phone_code:'+33',currency_code:'EUR'},{code:'CA',name:'Canada',phone_code:'+1',currency_code:'CAD'}], cities:[{id:'1',name:'Abidjan',country_code:'CI'}], districts:[] }), state: ref('success'), error: ref(''), load: vi.fn() }) }))
const user = {id:'1',first_name:'Awa',last_name:'Koné',name:'Awa Koné',email:'awa@example.test',phone:'+2250701020304',country_code:'CI',role:'customer',demo_mode:true,email_verification_required:false}
const mounted=[]
async function page(component, url='/account', meta={}) {
 const router=createRouter({history:createMemoryHistory(),routes:[{path:url,component,meta},{path:'/:pathMatch(.*)*',component:{template:'<p>Destination</p>'}}]})
 await router.push(url)
 const wrapper=mount(component,{global:{plugins:[router]}});mounted.push(wrapper);await flushPromises();return {wrapper,router}
}
beforeEach(()=>{vi.clearAllMocks();clearAuth();auth.user={...user};appConfig.demo_mode=true;appConfig.default_country='CI';post.mockResolvedValue({data:user});request.mockResolvedValue({data:user});get.mockResolvedValue({data:{demo_mode:true,default_country:'CI'}});URL.createObjectURL=vi.fn(()=>'blob:preview');URL.revokeObjectURL=vi.fn()})
afterEach(()=>mounted.splice(0).forEach(w=>w.unmount()))
it('le sélecteur recherche les pays et suit leur indicatif',async()=>{
 const w=mount(CountryPhone,{props:{country:'CI',modelValue:''}});mounted.push(w)
 expect(w.get('.phone-prefix').text()).toBe('+225'); expect(w.find('.country-flag').exists()).toBe(true)
 await w.get('[type=search]').setValue('France');expect(w.findAll('option')).toHaveLength(2)
 await w.get('select').setValue('FR');expect(w.emitted('update:country')[0]).toEqual(['FR'])
 await w.setProps({country:'FR'});expect(w.get('.phone-prefix').text()).toBe('+33')
 await w.get('[type=tel]').setValue('0612345678');expect(w.emitted('update:modelValue').at(-1)).toEqual(['+33612345678'])
})
it('un téléphone invalide est signalé sans masquer la saisie',async()=>{
 const w=mount(CountryPhone,{props:{country:'CI',modelValue:''}});mounted.push(w);await w.get('[type=tel]').setValue('12')
 expect(w.get('[type=tel]').attributes('aria-invalid')).toBe('true');expect(w.get('[role=status]').text()).toContain('valide')
})
it.each(['/account','/account/profile','/account/orders','/account/reservations','/account/favorites'])('le CTA marketplace reste disponible sur %s',async path=>{
 const {wrapper}=await page(AccountLayout,path);expect(wrapper.get('.marketplace-card a').attributes('href')).toBe('/vehicles')
})
it('la déconnexion de sidebar appelle le serveur et efface la session',async()=>{
 const {wrapper,router}=await page(AccountLayout);await wrapper.get('.sidebar-logout').trigger('click');await flushPromises()
 expect(post).toHaveBeenCalledWith('/auth/logout');expect(auth.user).toBeNull();expect(router.currentRoute.value.path).toBe('/login')
})
it('le profil envoie uniquement les champs modifiables',async()=>{
 const {wrapper}=await page(ProfilePage,'/account/profile');await wrapper.get('input[autocomplete=given-name]').setValue('Aya')
 await wrapper.get('form').trigger('submit');await flushPromises()
 expect(request).toHaveBeenCalledWith('/me/profile',expect.objectContaining({method:'PATCH',body:expect.objectContaining({first_name:'Aya',country_code:'CI'})}))
 expect(request.mock.calls.at(-1)[1].body.email).toBeUndefined();expect(wrapper.text()).not.toContain('Recevoir le lien')
})
it('la photo est prévisualisée puis envoyée en multipart',async()=>{
 const {wrapper}=await page(ProfilePage,'/account/profile');const input=wrapper.get('[type=file]')
 Object.defineProperty(input.element,'files',{value:[new File(['png'],'avatar.png',{type:'image/png'})]})
 await input.trigger('change');expect(wrapper.find('img[src="blob:preview"]').exists()).toBe(true)
 await wrapper.get('.profile-photo button').trigger('click');await flushPromises()
 expect(post).toHaveBeenCalledWith('/me/avatar',expect.any(FormData));expect(post.mock.calls.at(-1)[1].get('avatar').name).toBe('avatar.png')
})
it('une photo cassée revient aux initiales',async()=>{
 const w=mount(UserAvatar,{props:{user:{...user,avatar_url:'/bad.png'}}});mounted.push(w);await w.get('img').trigger('error');expect(w.text()).toBe('AK')
})
it('Login et Register ont des scènes distinctes et Register est inversé',async()=>{
 const a=await page(AuthPage,'/login',{authMode:'login'});const b=await page(AuthPage,'/register',{authMode:'register'})
 expect(a.wrapper.classes()).not.toContain('auth-register');expect(b.wrapper.classes()).toContain('auth-register')
 expect(a.wrapper.get('.auth-scene>img').attributes('src')).not.toBe(b.wrapper.get('.auth-scene>img').attributes('src'))
 expect(b.wrapper.findComponent(CountryPhone).exists()).toBe(true)
})
it('le parcours Pro crée une demande à quatre étapes avec devise dérivée',async()=>{
 const {wrapper}=await page(ProRegisterPage,'/pro/register')
 expect(wrapper.findAll('.pro-steps li')).toHaveLength(4)
 await wrapper.get('form').trigger('submit');expect(wrapper.text()).toContain('Devise de vos annonces : XOF')
 await wrapper.get('form').trigger('submit');expect(wrapper.findAll('[type=file]')).toHaveLength(2)
 await wrapper.get('form').trigger('submit');expect(wrapper.text()).toContain('Créer mon espace professionnel')
 await wrapper.get('form').trigger('submit');await flushPromises()
 expect(post.mock.calls[0][0]).toBe('/auth/register-merchant');expect(post.mock.calls[0][1]).toBeInstanceOf(FormData)
 expect(post.mock.calls[0][1].has('role')).toBe(false);expect(post.mock.calls[0][1].has('shop[currency_code]')).toBe(false)
})
it('redirige par rôle avec priorité aux espaces professionnels',()=>{
 expect(loginDestination()).toBe('/account');auth.user.role='merchant';expect(loginDestination('/account')).toBe('/pro');auth.user.role='admin';expect(loginDestination()).toBe('/admin')
})
it('le catalogue privilégie le pays du profil sans remplacer un choix explicite',async()=>{
 expect(await authGuard({path:'/vehicles',meta:{},query:{}})).toMatchObject({query:{country_code:'CI'}})
 expect(await authGuard({path:'/vehicles',meta:{},query:{market:'all'}})).toBeUndefined()
 expect(await authGuard({path:'/vehicles',meta:{},query:{country_code:'FR'}})).toBeUndefined()
})
it.each([['XOF',0,'18500000','18 500 000 FCFA'],['EUR',2,'2890000','28 900 €'],['USD',2,'3290000','$32,900'],['CAD',2,'4100000','CA$41,000']])('affiche %s sans conversion', (currency,minor_unit,amount_minor,expected)=>{
 expect(formatMoney({currency,minor_unit,amount_minor}).replace(/[\u00a0\u202f]/g,' ')).toBe(expected)
})
