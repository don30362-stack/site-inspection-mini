import { createRouter, createWebHistory } from 'vue-router'
import App from '../App.vue'

export default createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [{ path: '/', component: App }],
})
