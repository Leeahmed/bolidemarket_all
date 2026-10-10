import { createApp } from 'vue'
import '@fontsource-variable/inter'
import '@fontsource-variable/sora'
import App from './App.vue'
import { router } from './router'
import { onUnauthorized } from './services/api'
import { auth } from './stores/auth'
import './style.css'
onUnauthorized(()=>{auth.user=null;auth.ready=true;router.replace({path:'/login',query:{redirect:router.currentRoute.value.fullPath}})})
createApp(App).use(router).mount('#app')
