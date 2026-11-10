<script setup lang="ts">
import { onBeforeUnmount } from 'vue'
import { http } from '../lib/http'
import { reportStatuses } from '../lib/reportOptions'
import type { ApiResponse, Report } from '../lib/reports'
import { useReportStatus } from '../lib/useReportStatus'

const props = defineProps<{ report: Report }>()
const emit = defineEmits<{ updated: [report: Report] }>()
const { selected, pending, error, success, save, cancel } = useReportStatus(
  props.report.status,
  async (status, signal) => (await http.patch<ApiResponse<Report>>(
    `/reports/${props.report.id}/status`, { status }, { signal },
  )).data.data,
)

async function updateStatus() {
  const report = await save()
  if (report) emit('updated', report)
}

onBeforeUnmount(cancel)
</script>

<template>
  <div class="mb-4">
    <label for="report-status" class="form-label fw-semibold">處理狀態</label>
    <select
      id="report-status"
      v-model="selected"
      class="form-select"
      style="min-height: 44px"
      :disabled="pending"
      :aria-busy="pending"
      aria-describedby="report-status-feedback"
      @change="updateStatus"
    >
      <option v-for="option in reportStatuses" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
    <div id="report-status-feedback" class="small mt-2" aria-live="polite">
      <span v-if="pending" class="text-secondary">正在儲存…</span>
      <span v-else-if="error" class="text-danger" role="alert">{{ error }}</span>
      <span v-else-if="success" class="text-success">{{ success }}</span>
    </div>
  </div>
</template>
