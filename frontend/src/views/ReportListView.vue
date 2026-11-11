<script setup lang="ts">
import { RouterLink, useRoute, useRouter } from "vue-router";
import { http } from "../lib/http";
import { sites, issueTypes, severities, reportStatuses } from "../lib/reportOptions";
import { formatReportDate } from "../lib/reports";
import type { ReportListResponse } from "../lib/reports";
import { useReportList } from "../lib/useReportList";
import SeverityBadge from "../components/SeverityBadge.vue";
import StatusBadge from "../components/StatusBadge.vue";

const {
  reports,
  meta,
  pages,
  filters,
  hasFilters,
  loading,
  error,
  notFound,
  loadReports,
  setFilter,
  clearFilters,
  setPage,
  detailLink,
  keywordDraft,
  keywordComposing,
  keywordError,
  keywordQueryError,
  submitKeyword,
} = useReportList(useRoute(), useRouter(), async (params, signal) =>
  (await http.get<ReportListResponse>("/reports", { params, signal })).data,
);
</script>

<template>
  <main class="records-page container py-4 py-md-5">
    <header
      class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"
    >
      <div>
        <p class="eyebrow mb-2">現場紀錄</p>
        <h1 class="fw-bold mb-2">巡檢紀錄</h1>
        <p class="text-secondary mb-0">掌握現場狀況，依最新回報時間排序。</p>
      </div>
      <RouterLink to="/reports/new" class="btn btn-primary touch-control"
        >＋ 新增回報</RouterLink
      >
    </header>

    <section class="card border-0 shadow-sm mb-4" aria-label="篩選巡檢紀錄">
      <div class="card-body p-3 p-md-4">
        <form class="mb-3" @submit.prevent="submitKeyword">
          <label for="filter-keyword" class="form-label fw-semibold">描述關鍵字</label>
          <div class="d-flex gap-2">
            <input
              id="filter-keyword"
              v-model="keywordDraft"
              type="search"
              class="form-control"
              placeholder="搜尋問題描述"
              :aria-invalid="keywordError ? 'true' : undefined"
              aria-describedby="keyword-help keyword-error"
              @compositionstart="keywordComposing = true"
              @compositionend="keywordComposing = false"
              @keydown.enter="($event.isComposing || keywordComposing) && $event.preventDefault()"
            >
            <button type="submit" class="btn btn-primary touch-control text-nowrap">搜尋</button>
          </div>
          <p id="keyword-help" class="form-text mb-0">最多 100 個字元，按搜尋或 Enter 套用。</p>
          <p v-if="keywordError" id="keyword-error" class="text-danger small mb-0" role="alert">{{ keywordError }}</p>
        </form>
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-4 col-lg-3">
            <label for="filter-site" class="form-label fw-semibold">工地</label>
            <select
              id="filter-site"
              class="form-select touch-control"
              :value="filters.site_name"
              @change="setFilter('site_name', ($event.target as HTMLSelectElement).value)"
            >
              <option value="">全部工地</option>
              <option v-for="site in sites" :key="site" :value="site">
                {{ site }}
              </option>
            </select>
          </div>
          <div class="col-6 col-md-4 col-lg-3">
            <label for="filter-issue" class="form-label fw-semibold"
              >問題類型</label
            >
            <select
              id="filter-issue"
              class="form-select touch-control"
              :value="filters.issue_type"
              @change="setFilter('issue_type', ($event.target as HTMLSelectElement).value)"
            >
              <option value="">全部類型</option>
              <option v-for="issue in issueTypes" :key="issue" :value="issue">
                {{ issue }}
              </option>
            </select>
          </div>
          <div class="col-6 col-md-4 col-lg-3">
            <label for="filter-severity" class="form-label fw-semibold"
              >嚴重度</label
            >
            <select
              id="filter-severity"
              class="form-select touch-control"
              :value="filters.severity"
              @change="setFilter('severity', ($event.target as HTMLSelectElement).value)"
            >
              <option value="">全部嚴重度</option>
              <option
                v-for="severity in severities"
                :key="severity.value"
                :value="severity.value"
              >
                {{ severity.label }}
              </option>
            </select>
          </div>
          <div class="col-6 col-md-4 col-lg-3">
            <label for="filter-status" class="form-label fw-semibold">處理狀態</label>
            <select
              id="filter-status"
              class="form-select touch-control"
              :value="filters.status"
              @change="setFilter('status', ($event.target as HTMLSelectElement).value)"
            >
              <option value="">全部狀態</option>
              <option v-for="option in reportStatuses" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
          <div class="col-12 col-md-4 col-lg-3">
            <button
              type="button"
              class="btn btn-outline-secondary touch-control w-100"
              :disabled="!hasFilters && !$route.query.page && !keywordDraft"
              @click="clearFilters"
            >
              清除篩選
            </button>
          </div>
        </div>
      </div>
    </section>

    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
      <p class="mb-0 text-secondary" aria-live="polite">
        {{
          loading
            ? "正在取得紀錄…"
            : meta
              ? `共 ${meta.total} 筆紀錄，第 ${meta.current_page} / ${meta.last_page} 頁`
              : "巡檢紀錄"
        }}
      </p>
      <button
        type="button"
        class="btn btn-sm btn-outline-secondary touch-control"
        :disabled="loading"
        @click="loadReports"
      >
        重新載入
      </button>
    </div>
    <section v-if="keywordQueryError" class="state-panel card border-0 p-4 text-center">
      <p class="mb-0">請縮短描述關鍵字後重新搜尋。</p>
    </section>
    <section
      v-else-if="loading"
      class="state-panel card border-0 p-5 text-center"
      role="status"
      aria-live="polite"
    >
      <span
        class="spinner-border text-primary mx-auto mb-3"
        aria-hidden="true"
      ></span
      >正在載入巡檢紀錄…
    </section>
    <section
      v-else-if="error || notFound"
      class="alert alert-danger p-4"
      role="alert"
    >
      <p>{{ error || "暫時無法載入資料，請確認網路連線後重新載入。" }}</p>
      <button
        type="button"
        class="btn btn-outline-danger touch-control"
        @click="loadReports"
      >
        重新載入紀錄
      </button>
    </section>
    <section
      v-else-if="reports?.length === 0"
      class="state-panel card border-0 p-4 p-md-5 text-center"
    >
      <h2 class="h5">
        {{ hasFilters ? "沒有符合條件的巡檢紀錄" : "還沒有巡檢紀錄" }}
      </h2>
      <p class="text-secondary">
        {{
          hasFilters
            ? "試著調整或清除篩選條件。"
            : "從現場新增第一筆回報，紀錄就會出現在這裡。"
        }}
      </p>
      <button
        v-if="hasFilters"
        type="button"
        class="btn btn-outline-secondary touch-control align-self-center"
        @click="clearFilters"
      >
        清除目前篩選
      </button>
      <RouterLink
        v-else
        to="/reports/new"
        class="btn btn-primary touch-control align-self-center"
        >新增第一筆回報</RouterLink
      >
    </section>
    <template v-else-if="reports">
      <div class="d-none d-md-block card border-0 shadow-sm table-responsive">
        <table class="table align-middle mb-0">
          <caption class="visually-hidden">
            巡檢紀錄，依建立時間由新到舊；時間為台灣時間。
          </caption>
          <thead>
            <tr>
              <th scope="col">建立時間（台灣）</th>
              <th scope="col">工地</th>
              <th scope="col">問題類型</th>
              <th scope="col">嚴重度</th>
              <th scope="col">處理狀態</th>
              <th scope="col">照片</th>
              <th scope="col"><span class="visually-hidden">操作</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="report in reports" :key="report.id">
              <td>
                <time
                  :datetime="report.created_at || undefined"
                  class="text-nowrap small"
                  >{{ formatReportDate(report.created_at) }}</time
                >
              </td>
              <td>
                <RouterLink
                  :to="detailLink(report.id)"
                  class="record-site fw-semibold"
                  >{{ report.site_name }}</RouterLink
                ><span class="d-block small text-secondary"
                  >#{{ report.id }}</span
                >
              </td>
              <td>{{ report.issue_type }}</td>
              <td><SeverityBadge :severity="report.severity" /></td>
              <td><StatusBadge :status="report.status" /></td>
              <td class="text-nowrap">{{ report.images.length }} 張</td>
              <td>
                <RouterLink
                  :to="detailLink(report.id)"
                  class="btn btn-outline-primary text-nowrap touch-control"
                  :aria-label="`查看巡檢紀錄 #${report.id}`"
                  >查看詳細</RouterLink
                >
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="d-md-none d-grid gap-3">
        <RouterLink
          v-for="report in reports"
          :key="report.id"
          :to="detailLink(report.id)"
          class="record-card card border-0 shadow-sm p-3 text-decoration-none"
          :aria-label="`查看巡檢紀錄 #${report.id}`"
        >
          <div
            class="d-flex justify-content-between align-items-start gap-3 mb-2"
          >
            <h2 class="h6 fw-bold mb-0">{{ report.site_name }}</h2>
            <SeverityBadge :severity="report.severity" />
          </div>
          <div class="mb-2"><StatusBadge :status="report.status" /></div>
          <p class="mb-2">
            {{ report.issue_type }}
            <span class="text-secondary"
              >· {{ report.images.length }} 張照片</span
            >
          </p>
          <time
            :datetime="report.created_at || undefined"
            class="small text-secondary"
            >{{ formatReportDate(report.created_at) }}（台灣時間）</time
          >
          <div class="d-flex justify-content-between small mt-3">
            <span class="text-secondary">#{{ report.id }}</span
            ><span class="fw-semibold">查看詳細 →</span>
          </div>
        </RouterLink>
      </div>
    </template>
    <nav
      v-if="meta && meta.total > 0 && !loading && !error"
      class="mt-4"
      aria-label="巡檢列表分頁"
    >
      <ul class="pagination justify-content-center mb-0">
        <li class="page-item" :class="{ disabled: meta.current_page === 1 }">
          <button
            type="button"
            class="page-link touch-control"
            :disabled="meta.current_page === 1"
            @click="setPage(meta.current_page - 1)"
          >上一頁</button>
        </li>
        <li
          v-for="number in pages"
          :key="number"
          class="page-item"
          :class="{ active: number === meta.current_page }"
        >
          <button
            type="button"
            class="page-link touch-control"
            :aria-label="`第 ${number} 頁`"
            :aria-current="number === meta.current_page ? 'page' : undefined"
            @click="setPage(number)"
          >{{ number }}</button>
        </li>
        <li
          class="page-item"
          :class="{ disabled: meta.current_page >= meta.last_page }"
        >
          <button
            type="button"
            class="page-link touch-control"
            :disabled="meta.current_page >= meta.last_page"
            @click="setPage(meta.current_page + 1)"
          >下一頁</button>
        </li>
      </ul>
    </nav>
  </main>
</template>

<style scoped>
.records-page {
  max-width: 1200px;
}
.eyebrow {
  color: #18786e;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.13em;
}
h1 {
  font-size: clamp(1.7rem, 4vw, 2rem);
  color: #192f3a;
}
.card {
  border-radius: 1rem;
}
.touch-control {
  min-width: 44px;
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
select.touch-control {
  display: block;
}
th,
td {
  padding: 1rem;
}
th {
  background: #e8eef1;
  font-size: 0.85rem;
  color: #3d5663;
}
.record-site,
.record-card {
  color: #192f3a;
}
.record-site:hover,
.record-card:hover {
  color: #146b61;
}
.record-card:focus-visible {
  outline: 3px solid #18786e;
  outline-offset: 3px;
}
.state-panel {
  min-height: 220px;
}
</style>
