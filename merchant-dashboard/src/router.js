import { createRouter, createWebHistory } from 'vue-router'
import { merchantGuard } from './stores/auth'
const routes = [
  {path:'/login',component:()=>import('./pages/LoginPage.vue'),meta:{title:'Connexion'}},
  {path:'/',component:()=>import('./components/ProShell.vue'),children:[
    {path:'',redirect:'/dashboard'},
    {path:'dashboard',component:()=>import('./pages/DashboardPage.vue'),meta:{title:'Vue d’ensemble'}},
    {path:'vehicles',component:()=>import('./pages/VehiclesPage.vue'),meta:{title:'Véhicules'}},
    {path:'vehicles/create',component:()=>import('./pages/VehicleFormPage.vue'),meta:{title:'Ajouter un véhicule'}},
    {path:'vehicles/:id/edit',component:()=>import('./pages/VehicleFormPage.vue'),meta:{title:'Modifier le véhicule'}},
    {path:'vehicles/:id',component:()=>import('./pages/VehicleDetailPage.vue'),meta:{title:'Fiche véhicule'}},
    ...['reservations','orders','rentals'].map(path=>({path,component:()=>import('./pages/CommercePage.vue'),meta:{title:{reservations:'Réservations',orders:'Ventes',rentals:'Locations'}[path]}})),
    ...['reservations','orders'].map(path=>({path:path+'/:id',component:()=>import('./pages/CommerceDetailPage.vue'),meta:{title:path==='orders'?'Détail de vente':'Détail de réservation'}})),
    {path:'clients',component:()=>import('./pages/ClientsPage.vue'),meta:{title:'Clients'}},
    {path:'shop',component:()=>import('./pages/ShopPage.vue'),meta:{title:'Ma boutique'}},
    {path:'profile',component:()=>import('./pages/ProfilePage.vue'),meta:{title:'Mon profil'}},
    {path:'settings',component:()=>import('./pages/SettingsPage.vue'),meta:{title:'Paramètres'}},
    {path:':pathMatch(.*)*',component:()=>import('./pages/NotFoundPage.vue'),meta:{title:'Page introuvable'}},
  ]},
]
export const router = createRouter({history:createWebHistory(),routes,scrollBehavior:()=>({top:0})})
router.beforeEach(merchantGuard)
router.afterEach(to=>{document.title=to.meta.title+' · BolideMarket Pro'})
