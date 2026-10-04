import { createApp } from 'vue'
import '@fontsource-variable/inter'
import '@fontsource-variable/sora'
import './style.css'
import App from './App.vue'
import { router } from './router'
import { onUnauthorized } from './services/api'
import { auth } from './stores/auth'
import { resetShops } from './stores/shop'
onUnauthorized(()=>{auth.user=null;auth.ready=true;resetShops();router.replace('/login')})
createApp(App).use(router).mount('#app')
