<script setup lang="ts">
import { RouterLink } from "vue-router";
</script>

<template>
  <main
    class="not-found-page container d-flex align-items-center justify-content-center py-5"
  >
    <section
      class="not-found-card card border-0 shadow-sm p-4 p-md-5 text-center"
      aria-labelledby="not-found-heading"
    >
      <span class="page-icon mx-auto mb-3" aria-hidden="true">
        <svg
          width="40"
          height="40"
          viewBox="0 0 48 48"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path
            d="M28 6H13a3 3 0 0 0-3 3v30a3 3 0 0 0 3 3h22a3 3 0 0 0 3-3V16L28 6Z"
          />
          <path d="M28 6v10h10M18 25h12M18 31h7" />
        </svg>
      </span>
      <p class="error-code mb-2">404</p>
      <h1 id="not-found-heading" class="h3 fw-bold mb-3">找不到這個頁面</h1>
      <p class="text-secondary mb-4">網址可能輸入錯誤，或頁面已經不存在。</p>
      <div class="d-grid d-sm-flex justify-content-center gap-3">
        <RouterLink to="/reports" class="btn btn-primary"
          >查看巡檢紀錄</RouterLink
        >
        <RouterLink to="/reports/new" class="btn btn-outline-secondary"
          >新增巡檢回報</RouterLink
        >
      </div>
    </section>
  </main>
</template>

<style scoped>
.not-found-page {
  min-height: calc(100svh - 106px);
  max-width: 760px;
}
.not-found-card {
  width: 100%;
  border-radius: 1rem;
}
.page-icon {
  display: grid;
  place-items: center;
  width: 76px;
  height: 76px;
  border-radius: 50%;
  background: #e6f3ef;
  color: #146b61;
}
.error-code {
  color: #18786e;
  font-size: clamp(3rem, 10vw, 4rem);
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: 0.04em;
}
h1 {
  color: #192f3a;
}
.btn {
  min-height: 48px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.7rem 1.25rem;
}
@media (min-width: 576px) {
  .not-found-page {
    min-height: calc(100svh - 76px);
  }
}
</style>
