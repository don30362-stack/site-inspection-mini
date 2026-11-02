<script setup lang="ts">
import { RouterView } from 'vue-router'
</script>

<template>
  <div class="app-shell">
    <div class="brand-bar px-3 py-3"><div class="brand-inner mx-auto">SITE INSPECTION <span>MINI</span></div></div>
    <RouterView />
  </div>
</template>

<style>
body { margin: 0; background: #f1f4f6; color: #263e4b; font-family: system-ui, -apple-system, "Microsoft JhengHei", sans-serif; }
.app-shell { min-height: 100dvh; }
.brand-bar { background: #192f3a; color: #fff; font-size: .75rem; font-weight: 700; letter-spacing: .12em; }
.brand-inner { max-width: 656px; }
.brand-inner span { color: #81d5c2; }
</style>
