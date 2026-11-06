<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref } from "vue";
import { isAxiosError } from "axios";
import { RouterLink } from "vue-router";
import { http } from "../lib/http";
import { MAX_PHOTOS, usePhotoCollection } from "../lib/photoCollection";
import { sites, issueTypes, severities } from "../lib/reportOptions";
import type { ApiResponse, Report } from "../lib/reports";

type Field = "site_name" | "issue_type" | "severity" | "photos" | "description";
type FieldErrors = Partial<Record<Field, string>>;
type ApiResult = ApiResponse<Pick<Report, "id"> | null>;

const form = reactive({
  site_name: "",
  issue_type: "",
  severity: "",
  description: "",
});
const errors = ref<FieldErrors>({});
const {
  items: photos,
  photoError,
  processingPhoto,
  addPhotos,
  replacePhoto,
  removePhoto,
  clearPhotos,
  movePhoto,
} = usePhotoCollection();
const submitting = ref(false);
const errorMessage = ref("");
const successId = ref<number | null>(null);
const addInput = ref<HTMLInputElement | null>(null);
const replaceInput = ref<HTMLInputElement | null>(null);
const formElement = ref<HTMLFormElement | null>(null);
const busy = computed(() => processingPhoto.value || submitting.value);
const descriptionCount = computed(() => Array.from(form.description).length);
let replaceTarget: number | null = null;
let requestController: AbortController | null = null;
const dragging = ref<{ key: number; target: number; pointer: number } | null>(
  null,
);
const sortMessage = ref("");
let dragFrame = 0;
let dragPoint = { x: 0, y: 0 };

function updateDragTarget() {
  const cell = document
    .elementFromPoint(dragPoint.x, dragPoint.y)
    ?.closest<HTMLElement>("[data-photo-position]");
  if (cell && formElement.value?.contains(cell) && dragging.value)
    dragging.value.target = Number(cell.dataset.photoPosition);
}

function scrollWhileDragging() {
  if (!dragging.value) return;
  const edge = 64;
  const delta =
    dragPoint.y < edge ? -12 : dragPoint.y > window.innerHeight - edge ? 12 : 0;
  if (delta) window.scrollBy(0, delta);
  updateDragTarget();
  dragFrame = requestAnimationFrame(scrollWhileDragging);
}

function startDrag(event: PointerEvent, key: number, index: number) {
  if (busy.value || photos.length < 2 || !event.isPrimary || event.button !== 0)
    return;
  const handle = event.currentTarget as HTMLButtonElement;
  event.preventDefault();
  handle.focus();
  handle.setPointerCapture(event.pointerId);
  dragging.value = { key, target: index, pointer: event.pointerId };
  dragPoint = { x: event.clientX, y: event.clientY };
  dragFrame = requestAnimationFrame(scrollWhileDragging);
}

function dragOver(event: PointerEvent) {
  if (!dragging.value || dragging.value.pointer !== event.pointerId) return;
  dragPoint = { x: event.clientX, y: event.clientY };
  updateDragTarget();
}

function finishDrag(event: PointerEvent, cancelled = false) {
  const drag = dragging.value;
  if (!drag || drag.pointer !== event.pointerId) return;
  const cell = document
    .elementFromPoint(event.clientX, event.clientY)
    ?.closest<HTMLElement>("[data-photo-position]");
  dragging.value = null;
  cancelAnimationFrame(dragFrame);
  if (
    !cancelled &&
    !busy.value &&
    cell &&
    formElement.value?.contains(cell) &&
    movePhoto(drag.key, Number(cell.dataset.photoPosition))
  ) {
    sortMessage.value = `照片已移至第 ${Number(cell.dataset.photoPosition) + 1} 張。`;
  }
}

async function sortWithKeyboard(
  event: KeyboardEvent,
  key: number,
  index: number,
) {
  const offsets: Record<string, number> = {
    ArrowLeft: -1,
    ArrowRight: 1,
    ArrowUp: -2,
    ArrowDown: 2,
  };
  const offset = offsets[event.key];
  if (offset === undefined) return;
  event.preventDefault();
  if (busy.value || dragging.value || !movePhoto(key, index + offset)) return;
  sortMessage.value = `照片已移至第 ${index + offset + 1} 張。`;
  await nextTick();
  formElement.value
    ?.querySelector<HTMLButtonElement>(`[data-sort-key="${key}"]`)
    ?.focus();
}

function choosePhotos() {
  if (busy.value || !addInput.value) return;
  addInput.value.value = "";
  addInput.value.click();
}

function chooseReplacement(key: number) {
  if (busy.value || !replaceInput.value) return;
  replaceTarget = key;
  replaceInput.value.value = "";
  replaceInput.value.click();
}

async function selectPhotos(event: Event) {
  const files = Array.from((event.target as HTMLInputElement).files || []);
  if (busy.value) return;
  delete errors.value.photos;
  await addPhotos(files);
}

async function selectReplacement(event: Event) {
  const source = (event.target as HTMLInputElement).files?.[0];
  const key = replaceTarget;
  replaceTarget = null;
  if (key === null || busy.value) return;
  delete errors.value.photos;
  await replacePhoto(key, source);
}

async function focusFirstError() {
  await nextTick();
  formElement.value
    ?.querySelector<HTMLElement>('[aria-invalid="true"]')
    ?.focus();
}

async function submitReport() {
  if (busy.value || dragging.value || successId.value !== null) return;
  errors.value = {};
  errorMessage.value = "";
  for (const photo of photos) photo.error = "";
  if (!sites.includes(form.site_name)) errors.value.site_name = "請選擇工地。";
  if (!issueTypes.includes(form.issue_type))
    errors.value.issue_type = "請選擇問題類型。";
  if (!severities.some((item) => item.value === form.severity))
    errors.value.severity = "請選擇嚴重度。";
  if (photos.length === 0) errors.value.photos = "請至少新增一張現場照片。";
  const description = form.description.trim();
  if (!description) errors.value.description = "請填寫問題描述。";
  else if (Array.from(description).length > 1000)
    errors.value.description = "問題描述不得超過 1,000 字。";
  if (Object.keys(errors.value).length) {
    await focusFirstError();
    return;
  }

  const data = new FormData();
  data.append("site_name", form.site_name);
  data.append("issue_type", form.issue_type);
  data.append("severity", form.severity);
  data.append("description", description);
  for (const photo of photos) data.append("photos[]", photo.file!);
  submitting.value = true;
  requestController = new AbortController();
  try {
    const response = await http.post<ApiResult>("/reports", data, {
      signal: requestController.signal,
    });
    if (!response.data.data?.id)
      throw new Error("伺服器回應不完整，請先確認是否已建立紀錄，再重試。");
    successId.value = response.data.data.id;
    clearPhotos();
  } catch (error) {
    if (isAxiosError<ApiResult>(error)) {
      if (error.code === "ERR_CANCELED") return;
      if (error.response?.status === 422) {
        for (const field of [
          "site_name",
          "issue_type",
          "severity",
          "description",
          "photos",
        ] as const) {
          const messages = error.response.data?.errors?.[field];
          if (Array.isArray(messages)) errors.value[field] = messages.join(" ");
        }
        for (const [field, messages] of Object.entries(
          error.response.data?.errors || {},
        )) {
          const match = /^photos\.(\d+)$/.exec(field);
          if (match && Array.isArray(messages)) {
            const photo = photos[Number(match[1])];
            if (photo) photo.error = messages.join(" ");
          }
        }
        errorMessage.value = "資料尚未送出成功，請檢查下方欄位。";
      } else if (error.response?.status === 429) {
        errorMessage.value =
          "每個來源 IP 每分鐘最多新增嘗試 10 次，請稍後再試；已填資料與照片仍保留。";
      } else if (error.response?.status === 409) {
        errorMessage.value =
          "照片儲存忙碌中，請稍後再試；已填資料與照片仍保留。";
      } else if (!error.response) {
        errorMessage.value =
          "網路連線失敗，尚未確認是否送出成功。請確認連線與紀錄後再重試。";
      } else {
        errorMessage.value =
          "伺服器暫時無法處理，請稍後再試；已填資料與照片仍保留。";
      }
    } else {
      errorMessage.value =
        error instanceof Error ? error.message : "送出失敗，請稍後再試。";
    }
  } finally {
    submitting.value = false;
    requestController = null;
    if (Object.keys(errors.value).length || photos.some((photo) => photo.error))
      await focusFirstError();
  }
}

function startNextReport() {
  successId.value = null;
  form.issue_type = "";
  form.severity = "";
  form.description = "";
  errors.value = {};
  errorMessage.value = "";
}

onBeforeUnmount(() => {
  cancelAnimationFrame(dragFrame);
  dragging.value = null;
  clearPhotos();
  requestController?.abort();
});
</script>

<template>
  <main class="report-page container py-4 py-md-5">
    <header class="mb-4">
      <p class="eyebrow mb-2">現場巡檢</p>
      <h1 class="fw-bold mb-2">新增巡檢回報</h1>
      <p class="text-secondary mb-0">拍下現場狀況，讓問題被看見、被處理。</p>
    </header>

    <section
      v-if="successId !== null"
      class="card border-0 shadow-sm p-4 p-md-5"
      role="status"
      aria-live="polite"
    >
      <span class="success-mark mb-3" aria-hidden="true">✓</span>
      <h2 class="h4 fw-bold">回報已送出</h2>
      <p class="text-secondary">
        巡檢紀錄 #{{ successId }} 已建立，照片已保存。
      </p>
      <RouterLink
        :to="`/reports/${successId}`"
        class="btn btn-primary btn-lg mt-2"
        >查看這筆紀錄</RouterLink
      >
      <button
        class="btn btn-outline-secondary btn-lg mt-2"
        type="button"
        @click="startNextReport"
      >
        建立下一筆回報
      </button>
    </section>

    <form
      v-else
      ref="formElement"
      class="card border-0 shadow-sm"
      novalidate
      :aria-busy="busy"
      @submit.prevent="submitReport"
    >
      <div class="card-body p-3 p-sm-4 p-md-5">
        <p class="small text-secondary mb-4">所有欄位皆為必填。</p>
        <div v-if="errorMessage" class="alert alert-danger" role="alert">
          {{ errorMessage }}
        </div>
        <fieldset :disabled="busy">
          <legend class="visually-hidden">巡檢回報資料</legend>
          <div class="mb-4">
            <label for="site" class="form-label fw-semibold">工地</label>
            <select
              id="site"
              v-model="form.site_name"
              class="form-select form-select-lg"
              :class="{ 'is-invalid': errors.site_name }"
              :aria-invalid="!!errors.site_name"
              aria-describedby="site-error"
              required
              @change="delete errors.site_name"
            >
              <option disabled value="">請選擇工地</option>
              <option v-for="site in sites" :key="site" :value="site">
                {{ site }}
              </option>
            </select>
            <div
              v-if="errors.site_name"
              id="site-error"
              class="invalid-feedback"
            >
              {{ errors.site_name }}
            </div>
          </div>

          <div class="mb-4">
            <label for="issue" class="form-label fw-semibold">問題類型</label>
            <select
              id="issue"
              v-model="form.issue_type"
              class="form-select form-select-lg"
              :class="{ 'is-invalid': errors.issue_type }"
              :aria-invalid="!!errors.issue_type"
              aria-describedby="issue-error"
              required
              @change="delete errors.issue_type"
            >
              <option disabled value="">請選擇問題類型</option>
              <option v-for="issue in issueTypes" :key="issue" :value="issue">
                {{ issue }}
              </option>
            </select>
            <div
              v-if="errors.issue_type"
              id="issue-error"
              class="invalid-feedback"
            >
              {{ errors.issue_type }}
            </div>
          </div>

          <fieldset class="mb-4">
            <legend class="form-label fs-6 fw-semibold">嚴重度</legend>
            <div class="row g-2">
              <div
                v-for="severity in severities"
                :key="severity.value"
                class="col-4"
              >
                <input
                  :id="`severity-${severity.value}`"
                  v-model="form.severity"
                  class="btn-check"
                  type="radio"
                  name="severity"
                  :value="severity.value"
                  :aria-invalid="!!errors.severity"
                  aria-describedby="severity-error"
                  required
                  @change="delete errors.severity"
                />
                <label
                  :for="`severity-${severity.value}`"
                  class="severity-choice btn w-100 py-3"
                  :class="`severity-${severity.value}`"
                  >{{ severity.label }}</label
                >
              </div>
            </div>
            <p
              v-if="errors.severity"
              id="severity-error"
              class="text-danger small mt-2 mb-0"
            >
              {{ errors.severity }}
            </p>
          </fieldset>

          <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span id="photos-label" class="form-label fw-semibold mb-0"
                >現場照片 <span class="text-danger">*</span></span
              >
              <span class="small text-secondary" aria-live="polite"
                >{{ photos.length }} / 5 張</span
              >
            </div>
            <div
              class="photo-grid"
              role="group"
              aria-labelledby="photos-label"
              aria-describedby="photo-hint photo-error"
              :aria-invalid="!!errors.photos"
              tabindex="-1"
            >
              <div
                v-for="(photo, index) in photos"
                :key="photo.key"
                class="photo-cell"
                :data-photo-position="index"
                :class="{
                  'sort-source': dragging?.key === photo.key,
                  'sort-target': dragging?.target === index,
                }"
              >
                <div
                  class="photo-tile"
                  :class="{ 'photo-invalid': photo.error }"
                >
                  <button
                    type="button"
                    class="photo-replace"
                    :aria-label="`更換第 ${index + 1} 張照片`"
                    :disabled="busy"
                    :aria-invalid="!!photo.error"
                    :aria-describedby="
                      photo.error ? `photo-error-${photo.key}` : undefined
                    "
                    @click="chooseReplacement(photo.key)"
                  >
                    <img
                      v-if="photo.url"
                      :src="photo.url"
                      :alt="`現場照片 ${index + 1}`"
                      draggable="false"
                    />
                    <span v-else class="visually-hidden"
                      >第 {{ index + 1 }} 張照片</span
                    >
                    <span v-if="photo.status === 'ready'" class="photo-caption"
                      >{{
                        photo.converted ? "已轉為 JPEG · " : ""
                      }}點擊更換</span
                    >
                  </button>
                  <span class="photo-number" aria-hidden="true">{{
                    index + 1
                  }}</span>
                  <button
                    v-if="photos.length > 1"
                    type="button"
                    class="photo-grip"
                    :data-sort-key="photo.key"
                    :aria-label="`排序第 ${index + 1} 張照片`"
                    aria-describedby="sort-hint"
                    :disabled="busy"
                    @pointerdown.stop="startDrag($event, photo.key, index)"
                    @pointermove="dragOver"
                    @pointerup="finishDrag($event)"
                    @pointercancel="finishDrag($event, true)"
                    @lostpointercapture="finishDrag($event, true)"
                    @keydown="sortWithKeyboard($event, photo.key, index)"
                    @keydown.esc.prevent="dragging = null"
                    @click.stop.prevent
                  >
                    <svg
                      width="20"
                      height="20"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                      aria-hidden="true"
                    >
                      <circle
                        v-for="(point, n) in [
                          [7, 5],
                          [13, 5],
                          [7, 10],
                          [13, 10],
                          [7, 15],
                          [13, 15],
                        ]"
                        :key="n"
                        :cx="point[0]"
                        :cy="point[1]"
                        r="1.5"
                      />
                    </svg>
                  </button>
                  <div
                    v-if="photo.status !== 'ready'"
                    class="photo-loading"
                    role="status"
                  >
                    <span
                      class="spinner-border spinner-border-sm"
                      aria-hidden="true"
                    ></span>
                    <span>{{
                      photo.status === "queued" ? "等待處理…" : "照片處理中…"
                    }}</span>
                  </div>
                  <button
                    v-if="photo.status === 'ready'"
                    type="button"
                    class="photo-delete"
                    :aria-label="`刪除第 ${index + 1} 張照片`"
                    :disabled="busy"
                    @click.stop="
                      removePhoto(photo.key);
                      delete errors.photos;
                    "
                  >
                    <svg
                      width="20"
                      height="20"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                      aria-hidden="true"
                    >
                      <path
                        d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 10v7M14 10v7"
                      />
                    </svg>
                  </button>
                </div>
                <p
                  v-if="photo.error"
                  :id="`photo-error-${photo.key}`"
                  class="small text-danger mt-2 mb-0"
                  role="alert"
                >
                  {{ photo.error }}
                </p>
              </div>
              <div v-if="photos.length < MAX_PHOTOS" class="photo-cell">
                <button
                  type="button"
                  class="photo-add photo-tile"
                  :disabled="busy"
                  @click="choosePhotos"
                >
                  <span class="photo-plus" aria-hidden="true">＋</span
                  ><span>新增照片</span>
                </button>
              </div>
            </div>
            <p v-if="photos.length > 1" id="sort-hint" class="form-text mb-0">
              拖曳排序圖示可調整照片順序<span class="d-block small"
                >鍵盤：左右鍵移動一格，上下鍵移動一列。</span
              >
            </p>
            <p
              v-if="dragging"
              class="small text-primary mt-2 mb-0"
              role="status"
            >
              放開可移至第 {{ dragging.target + 1 }} 張；按 Escape 可取消。
            </p>
            <span class="visually-hidden" role="status" aria-live="polite">{{
              sortMessage
            }}</span>
            <input
              ref="addInput"
              type="file"
              accept="image/*,.heic,.heif"
              multiple
              hidden
              aria-label="新增現場照片檔案"
              @change="selectPhotos"
            />
            <input
              ref="replaceInput"
              type="file"
              accept="image/*,.heic,.heif"
              hidden
              aria-label="更換現場照片檔案"
              @change="selectReplacement"
              @cancel="replaceTarget = null"
            />
            <p
              v-if="errors.photos"
              id="photo-error"
              class="small text-danger mt-2 mb-0"
              role="alert"
            >
              {{ errors.photos }}
            </p>
            <p
              v-if="photoError"
              class="small text-danger mt-2 mb-0"
              role="alert"
            >
              {{ photoError }}
            </p>
            <p id="photo-hint" class="form-text mb-0">
              至少 1 張、最多 5 張，每張最多 20
              MiB。可拍照或多選相簿照片；HEIC／HEIF 自動轉為 JPEG。
            </p>
          </div>

          <div class="mb-4">
            <label for="description" class="form-label fw-semibold"
              >問題描述</label
            >
            <textarea
              id="description"
              v-model="form.description"
              class="form-control"
              :class="{ 'is-invalid': errors.description }"
              rows="5"
              placeholder="例如：3 樓走道護欄鬆動，請協助安排修復。"
              :aria-invalid="!!errors.description"
              aria-describedby="description-count description-error"
              required
              @input="delete errors.description"
            ></textarea>
            <div
              v-if="errors.description"
              id="description-error"
              class="invalid-feedback"
            >
              {{ errors.description }}
            </div>
            <p
              id="description-count"
              class="small text-end mt-2 mb-0"
              :class="
                descriptionCount > 1000 ? 'text-danger' : 'text-secondary'
              "
            >
              {{ descriptionCount.toLocaleString() }} / 1,000 字
            </p>
          </div>
        </fieldset>

        <p
          v-if="processingPhoto"
          class="text-primary"
          role="status"
          aria-live="polite"
        >
          <span
            class="spinner-border spinner-border-sm me-2"
            aria-hidden="true"
          ></span
          >正在處理照片，請稍候…
        </p>
        <button
          type="submit"
          class="btn btn-primary btn-lg w-100 submit-button"
          :disabled="busy || !!dragging"
        >
          <span
            v-if="busy"
            class="spinner-border spinner-border-sm me-2"
            aria-hidden="true"
          ></span>
          {{
            submitting
              ? "正在送出…"
              : processingPhoto
                ? "照片處理中…"
                : "送出巡檢回報"
          }}
        </button>
        <p class="small text-secondary text-center mt-3 mb-0">
          送出前，請確認工地與現場照片正確。
        </p>
      </div>
    </form>
  </main>
</template>

<style scoped>
.report-page {
  max-width: 680px;
}
.eyebrow {
  color: #18786e;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.13em;
}
h1 {
  font-size: clamp(1.65rem, 5vw, 2rem);
  color: #192f3a;
}
.card {
  border-radius: 1rem;
}
.form-control,
.form-select {
  min-height: 48px;
}
.form-control:focus,
.form-select:focus {
  border-color: #18786e;
  box-shadow: 0 0 0 0.2rem rgb(24 120 110 / 15%);
}
.severity-choice {
  border: 1px solid #cad3d9;
  background: #fff;
  color: #334b5a;
  font-weight: 600;
}
.btn-check:checked + .severity-low {
  background: #e4f4eb;
  color: #22613d;
  border-color: #328557;
}
.btn-check:checked + .severity-medium {
  background: #fff1d5;
  color: #79520a;
  border-color: #aa7616;
}
.btn-check:checked + .severity-high {
  background: #fde7e6;
  color: #942e2a;
  border-color: #b5413a;
}
.photo-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.photo-tile {
  position: relative;
  width: 100%;
  aspect-ratio: 1;
  overflow: hidden;
  border: 1px solid #cbd6dc;
  border-radius: 12px;
  background: #edf2f4;
}
.photo-replace {
  width: 100%;
  height: 100%;
  padding: 0;
  border: 0;
  background: transparent;
}
.photo-replace img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.photo-caption {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 8px;
  background: rgb(14 32 40 / 70%);
  color: white;
  font-size: 0.75rem;
}
.photo-delete {
  position: absolute;
  top: 6px;
  right: 6px;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1px solid #dce2e6;
  border-radius: 50%;
  color: #9c342d;
  background: white;
  box-shadow: 0 2px 8px #0002;
}
.photo-grip {
  position: absolute;
  top: 6px;
  left: 6px;
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  border: 1px solid #dce2e6;
  border-radius: 10px;
  background: white;
  color: #192f3a;
  touch-action: none;
  cursor: grab;
  user-select: none;
}
.photo-grip:active {
  cursor: grabbing;
}
.photo-grip:focus-visible {
  outline: 3px solid #18786e;
  outline-offset: -4px;
}
.photo-number {
  position: absolute;
  right: 8px;
  bottom: 38px;
  min-width: 24px;
  text-align: center;
  border-radius: 5px;
  background: #192f3acc;
  color: white;
  pointer-events: none;
}
.sort-source .photo-tile {
  opacity: 0.65;
}
.sort-target .photo-tile {
  outline: 4px solid #18786e;
  outline-offset: -4px;
}
.photo-add {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border: 2px dashed #b4c8cc;
  background: #f6faf9;
  color: #146b61;
  font-weight: 600;
}
.photo-plus {
  font-size: 2rem;
  line-height: 1.3;
  font-weight: 400;
}
.photo-loading {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: rgb(238 245 245 / 90%);
  color: #146b61;
  font-size: 0.85rem;
}
.photo-invalid {
  border: 2px solid #b5413a;
}
.photo-replace:focus-visible,
.photo-add:focus-visible,
.photo-delete:focus-visible {
  outline: 3px solid #18786e;
  outline-offset: -4px;
}
.btn-primary {
  --bs-btn-bg: #146b61;
  --bs-btn-border-color: #146b61;
  --bs-btn-hover-bg: #10564e;
  --bs-btn-hover-border-color: #10564e;
  --bs-btn-disabled-bg: #146b61;
  --bs-btn-disabled-border-color: #146b61;
}
.submit-button {
  min-height: 56px;
  font-weight: 600;
}
.success-mark {
  display: inline-grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: #e4f4eb;
  color: #22613d;
  font-size: 1.8rem;
}
</style>
