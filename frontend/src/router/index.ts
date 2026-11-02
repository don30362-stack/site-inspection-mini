import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/reports/new' },
    { path: '/reports/new', component: () => import('../views/NewReportView.vue'), meta: { title: '新增巡檢回報' } },
    { path: '/reports', component: () => import('../views/ReportListView.vue'), meta: { title: '巡檢紀錄' } },
    { path: '/reports/:id', component: () => import('../views/ReportDetailView.vue'), meta: { title: '巡檢詳細資料' } },
    { path: '/:pathMatch(.*)*', component: () => import('../views/NotFoundView.vue'), meta: { title: '找不到頁面' } },
  ],
})

router.afterEach((to) => { document.title = `${to.meta.title || '巡檢回報'} · Site Inspection Mini` })

export default router
