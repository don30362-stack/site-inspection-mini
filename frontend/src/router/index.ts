import { createRouter, createWebHistory } from 'vue-router'

export default createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/reports/new' },
    { path: '/reports/new', component: () => import('../views/NewReportView.vue') },
  ],
})
