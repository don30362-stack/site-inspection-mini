import { createApp } from 'vue'
import 'bootstrap/dist/css/bootstrap.min.css'
import { RouterView } from 'vue-router'
import router from './router'

createApp(RouterView).use(router).mount('#app')
