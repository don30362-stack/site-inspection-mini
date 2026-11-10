<script setup lang="ts">
import { computed } from 'vue'
import { reportStatuses } from '../lib/reportOptions'
import type { ReportStatus } from '../lib/reportOptions'

const props = defineProps<{ status: ReportStatus }>()
const option = computed(() => reportStatuses.find(entry => entry.value === props.status))
</script>

<template>
  <span class="badge rounded-pill text-nowrap" :class="option?.badgeClass">
    {{ option?.label || '未知狀態' }}
  </span>
</template>
