<script setup lang="ts">
import type { Severity } from '../lib/reports'

defineProps<{ severity: Severity }>()
const labels = { low: '低', medium: '中', high: '高' }
</script>

<template>
  <span class="severity-badge" :class="`severity-${severity}`">{{ labels[severity] || '未知' }}</span>
</template>

<style scoped>
.severity-badge { display: inline-block; min-width: 44px; padding: .3rem .7rem; border-radius: 2rem; font-size: .85rem; font-weight: 700; text-align: center; }
.severity-low { background: #e4f4eb; color: #22613d; }
.severity-medium { background: #fff1d5; color: #79520a; }
.severity-high { background: #fde7e6; color: #942e2a; }
</style>
