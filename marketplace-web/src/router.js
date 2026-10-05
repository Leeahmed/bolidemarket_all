import { createRouter, createWebHistory } from 'vue-router'
import { authGuard, clearAuth } from './stores/auth'
import { onUnauthorized } from './services/api'
import { notify } from './stores/toasts'
export const routes = [
  { path: '/', redirect: to => ({ path: '/vehicles', query: to.query }) },
  { path: '/vehicles', component: () => import('./pages/CatalogPage.vue') },
  { path: '/vehicles/:slug', component: () => import('./pages/VehiclePage.vue') },
  { path: '/shops', component: () => import('./pages/ShopsPage.vue') },
  { path: '/shops/:slug', component: () => import('./pages/ShopPage.vue') },
  ...[['login', 'login'], ['register', 'register'], ['forgot-password', 'forgot'], ['reset-password', 'reset']].map(([path, authMode]) => ({ path: '/' + path, component: () => import('./pages/AuthPage.vue'), meta: { guest: true, authMode } })),
  { path: '/pro/register', component: () => import('./pages/ProRegisterPage.vue'), meta: { guest: true } },
  { path: '/pro/login', redirect: '/login' },
  { path: '/pro', component: () => import('./pages/RoleHomePage.vue'), meta: { auth: true, role: 'merchant' } },
  { path: '/admin', component: () => import('./pages/RoleHomePage.vue'), meta: { auth: true, role: 'admin' } },
  { path: '/vehicles/:slug/reserve', component: () => import('./pages/CheckoutPage.vue'), meta: { auth: true, checkout: 'reserve' } },
  { path: '/vehicles/:slug/buy', component: () => import('./pages/CheckoutPage.vue'), meta: { auth: true, checkout: 'buy' } },
  { path: '/reservation-confirmation/:id', component: () => import('./pages/RecordPage.vue'), meta: { auth: true, kind: 'reservations', confirmation: true } },
  { path: '/order-confirmation/:id', component: () => import('./pages/RecordPage.vue'), meta: { auth: true, kind: 'orders', confirmation: true } },
  { path: '/account', component: () => import('./components/AccountLayout.vue'), meta: { auth: true }, children: [
    { path: '', component: () => import('./pages/AccountPage.vue') },
    { path: 'receipts', component: () => import('./pages/ReceiptsPage.vue') },
    { path: 'receipts/:reference', component: () => import('./pages/ReceiptsPage.vue') },
    { path: 'profile', component: () => import('./pages/ProfilePage.vue') },
    { path: 'price-offers', component: () => import('./pages/PriceOffersPage.vue') },
    { path: 'favorites', component: () => import('./pages/FavoritesPage.vue') },
    ...['reservations', 'orders'].flatMap(kind => [
      { path: kind, component: () => import('./pages/HistoryPage.vue'), meta: { kind } },
      { path: kind + '/:id', component: () => import('./pages/RecordPage.vue'), meta: { kind } },
    ]),
  ] },
  { path: '/:pathMatch(.*)*', component: () => import('./pages/NotFoundPage.vue') },
]
export const router = createRouter({ history: createWebHistory(), routes, scrollBehavior: (to, from, saved) => saved || (to.path === from.path ? false : { top: 0 }) })
router.beforeEach(authGuard)
onUnauthorized(() => {
  clearAuth()
  if (router.currentRoute.value.meta.auth) {
    notify('Votre session a expiré. Reconnectez-vous pour continuer.')
    router.replace({ path: '/login', query: { redirect: router.currentRoute.value.fullPath } })
  }
})
router.afterEach(() => { document.title = 'Véhicules et professionnels | BolideMarket' })
