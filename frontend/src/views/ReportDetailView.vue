<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import { http } from "../lib/http";
import type { ApiResponse, Report } from "../lib/reports";
import {
  formatReportDate,
  readReportFilters,
  reportListQuery,
  readReportPage,
} from "../lib/reports";
import { useLatestRequest } from "../lib/useLatestRequest";
import SeverityBadge from "../components/SeverityBadge.vue";
import ReportGallery from "../components/ReportGallery.vue";
import DeleteReportDialog from "../components/DeleteReportDialog.vue";
import ReportStatusEditor from "../components/ReportStatusEditor.vue";

const route = useRoute();
const router = useRouter();
const backLink = computed(() => ({
  path: "/reports",
  query: reportListQuery(readReportFilters(route.query), readReportPage(route.query)),
}));
const {
  data: report,
  loading,
  error,
  notFound,
  run,
  cancel,
} = useLatestRequest<Report>();

function loadReport() {
  const id = String(route.params.id);
  return run(
    async (signal) =>
      (
        await http.get<ApiResponse<Report>>(
          `/reports/${encodeURIComponent(id)}`,
          { signal },
        )
      ).data.data,
  );
}

watch(() => route.params.id, loadReport, { immediate: true });
onBeforeUnmount(cancel);
</script>

<template>
  <main class="detail-page container py-4 py-md-5">
    <RouterLink
      :to="backLink"
      class="back-link d-inline-flex align-items-center mb-4"
      >← 返回巡檢列表</RouterLink
    >
    <section v-if="loading" class="card border-0 p-5 text-center" role="status">
      <span
        class="spinner-border text-primary mx-auto mb-3"
        aria-hidden="true"
      ></span
      >正在載入巡檢紀錄…
    </section>
    <section v-else-if="notFound" class="card border-0 p-4 p-md-5 text-center">
      <h1 class="h3 fw-bold">找不到巡檢紀錄</h1>
      <p class="text-secondary">這筆紀錄不存在，請返回列表查看其他回報。</p>
      <RouterLink
        :to="backLink"
        class="btn btn-primary align-self-center touch-control"
        >返回巡檢列表</RouterLink
      >
    </section>
    <section v-else-if="error" class="alert alert-danger p-4" role="alert">
      <h1 class="h4">巡檢紀錄載入失敗</h1>
      <p>{{ error }}</p>
      <button
        type="button"
        class="btn btn-outline-danger touch-control"
        @click="loadReport"
      >
        重新載入紀錄
      </button>
    </section>
    <template v-else-if="report">
      <header class="mb-4">
        <p class="eyebrow mb-2">巡檢紀錄 #{{ report.id }}</p>
        <h1 class="fw-bold mb-2">{{ report.site_name }}</h1>
        <p class="text-secondary mb-0">
          建立時間：<time :datetime="report.created_at || undefined">{{
            formatReportDate(report.created_at)
          }}</time
          >（台灣時間）
        </p>
        <p class="text-secondary mb-0">
          最後更新時間：<time :datetime="report.updated_at || undefined">{{
            formatReportDate(report.updated_at)
          }}</time
          >（台灣時間）
        </p>
      </header>
      <div class="row g-4">
        <div class="col-12 col-lg-4">
          <section
            class="card border-0 shadow-sm p-3 p-md-4 h-100"
            aria-labelledby="report-info-heading"
          >
            <h2 id="report-info-heading" class="h5 fw-bold mb-4">回報內容</h2>
            <ReportStatusEditor
              :key="report.id"
              :report="report"
              @updated="report = $event"
            />
            <dl class="mb-0">
              <dt>工地名稱</dt>
              <dd>{{ report.site_name }}</dd>
              <dt>問題類型</dt>
              <dd>{{ report.issue_type }}</dd>
              <dt>嚴重度</dt>
              <dd><SeverityBadge :severity="report.severity" /></dd>
              <dt>問題描述</dt>
              <dd class="description mb-0">{{ report.description }}</dd>
            </dl>
            <DeleteReportDialog
              :key="report.id"
              :report-id="report.id"
              @deleted="router.push(backLink)"
            />
          </section>
        </div>
        <div class="col-12 col-lg-8">
          <div class="card border-0 shadow-sm p-3 p-md-4">
            <ReportGallery :key="report.id" :images="report.images" />
          </div>
        </div>
      </div>
    </template>
  </main>
</template>

<style scoped>
.detail-page {
  max-width: 1200px;
}
.back-link {
  min-height: 44px;
  color: #146b61;
  text-decoration: none;
  font-weight: 600;
}
.eyebrow {
  color: #18786e;
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.05em;
}
h1 {
  font-size: clamp(1.7rem, 4vw, 2rem);
  color: #192f3a;
}
.card {
  border-radius: 1rem;
}
dt {
  font-size: 0.85rem;
  color: #667e89;
  font-weight: 500;
  margin-bottom: 0.4rem;
}
dd {
  margin-bottom: 1.5rem;
}
.description {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  line-height: 1.8;
}
.touch-control {
  min-height: 44px;
}
</style>
