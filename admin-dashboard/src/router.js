import { createRouter, createWebHistory } from 'vue-router'
import { adminGuard } from './stores/auth'
import { entities } from './entities'
const routes = [
 {path:'/',redirect:'/dashboard'},
 {path:'/login',component:()=>import('./pages/LoginPage.vue')},
 {path:'/forbidden',component:()=>import('./pages/ForbiddenPage.vue')},
 {path:'/dashboard',component:()=>import('./pages/DashboardPage.vue')},
 {path:'/reports',component:()=>import('./pages/DashboardPage.vue')},
 {path:'/settings',component:()=>import('./pages/SettingsPage.vue')},
 {path:'/search',component:()=>import('./pages/SearchPage.vue')},
 ...Object.keys(entities).flatMap(type=>[
  {path:'/'+type,component:()=>import('./pages/EntityList.vue'),props:{type}},
  ...(type==='activity'?[]:[{path:'/'+type+'/:id',component:()=>import('./pages/EntityDetail.vue'),props:r=>({type,id:r.params.id})}]),
 ]),
 {path:'/:pathMatch(.*)*',component:()=>import('./pages/NotFoundPage.vue')},
]
export const router=createRouter({history:createWebHistory(),routes,scrollBehavior:()=>({top:0})})
router.beforeEach(adminGuard)
