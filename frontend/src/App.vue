<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { readReportFilters, reportFilterQuery } from './lib/reports'

const route = useRoute()
const isRecordsPage = computed(() => route.matched.some(({ path }) => path === '/reports' || path === '/reports/:id'))
const recordsLink = computed(() => ({ path: '/reports', query: reportFilterQuery(readReportFilters(route.query)) }))
</script>

<template>
  <div class="app-shell">
    <header class="brand-bar px-3 py-3"><div class="brand-inner mx-auto d-flex flex-wrap justify-content-between align-items-center gap-3">
      <span class="brand-name">SITE INSPECTION <span>MINI</span></span>
      <nav class="app-nav" aria-label="主要導覽">
        <RouterLink to="/reports/new" :class="{ 'nav-current': route.path === '/reports/new' }" :aria-current="route.path === '/reports/new' ? 'page' : undefined">新增回報</RouterLink>
        <RouterLink :to="recordsLink" :class="{ 'nav-current': isRecordsPage }" :aria-current="isRecordsPage ? 'page' : undefined">巡檢紀錄</RouterLink>
      </nav>
    </div></header>
    <RouterView />
  </div>
</template>

<style>
body { margin: 0; background: #f1f4f6; color: #263e4b; font-family: system-ui, -apple-system, "Microsoft JhengHei", sans-serif; }
.app-shell { min-height: 100dvh; }
.brand-bar { background: #192f3a; color: #fff; font-size: .75rem; font-weight: 700; letter-spacing: .12em; }
.brand-inner { max-width: 1176px; }
.brand-name span { color: #81d5c2; }
.app-nav { display: flex; gap: 8px; width: 100%; }
.app-nav a { display: flex; align-items: center; justify-content: center; flex: 1; min-height: 44px; padding: 8px 16px; border-radius: 8px; color: #d7e6eb; text-decoration: none; font-size: .9rem; letter-spacing: normal; }
.app-nav a:hover { background: #29434f; color: white; }
.app-nav .nav-current { background: #146b61; color: white; box-shadow: inset 0 -3px #81d5c2; }
.app-nav a:focus-visible { outline: 2px solid #81d5c2; outline-offset: 3px; }
.btn-primary { --bs-btn-bg: #146b61; --bs-btn-border-color: #146b61; --bs-btn-hover-bg: #10564e; --bs-btn-hover-border-color: #10564e; }
.btn-outline-primary { --bs-btn-color: #146b61; --bs-btn-border-color: #146b61; --bs-btn-hover-bg: #146b61; --bs-btn-hover-border-color: #146b61; }
@media (min-width: 576px) { .app-nav { width: auto; } }
</style>
