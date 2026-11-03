<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref } from 'vue'
import { http } from '../lib/http'
import { useReportDeletion } from '../lib/useReportDeletion'

const props = defineProps<{ reportId: number }>()
const emit = defineEmits<{ deleted: [] }>()
const dialog = ref<HTMLDialogElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const passwordInput = ref<HTMLInputElement | null>(null)
const opened = ref(false)
let previousOverflow = ''
const { password, error, pending, submit, cancel } = useReportDeletion((value, signal) =>
  http.delete(`/reports/${props.reportId}`, { data: { admin_password: value }, signal }))

async function open() {
  cancel()
  previousOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  opened.value = true
  await nextTick()
  if (!opened.value || !dialog.value?.isConnected) return
  dialog.value.showModal()
  passwordInput.value?.focus()
}

function close(force = false) {
  if (pending.value && !force) return
  if (!opened.value) return
  opened.value = false
  dialog.value?.close()
  cancel()
  document.body.style.overflow = previousOverflow
  if (trigger.value?.isConnected) trigger.value.focus({ preventScroll: true })
}

async function confirmDelete() {
  if (await submit()) {
    close()
    emit('deleted')
  } else if (!pending.value && opened.value) {
    await nextTick()
    passwordInput.value?.focus()
  }
}

function trapFocus(event: KeyboardEvent) {
  if (event.key !== 'Tab') return
  const controls = dialog.value?.querySelectorAll<HTMLElement>('input:not(:disabled), button:not(:disabled)')
  const first = controls?.[0]
  const last = controls?.[controls.length - 1]
  if (!first || !last) { event.preventDefault(); return }
  if ((event.shiftKey && document.activeElement === first) || (!event.shiftKey && document.activeElement === last)) {
    event.preventDefault()
    const target = event.shiftKey ? last : first
    target.focus()
  }
}

onBeforeUnmount(() => close(true))
</script>

<template>
  <button ref="trigger" type="button" class="btn btn-outline-danger touch-control mt-3" @click="open">刪除這筆紀錄</button>
  <Teleport to="body">
    <dialog ref="dialog" class="delete-dialog" aria-labelledby="delete-title" aria-describedby="delete-description" @cancel.prevent="close()" @keydown="trapFocus">
      <form v-if="opened" :aria-busy="pending" novalidate @submit.prevent="confirmDelete">
        <h2 id="delete-title" class="h4 fw-bold">確認刪除巡檢紀錄</h2>
        <p id="delete-description">將刪除紀錄 #{{ reportId }} 與所有對應照片，此操作無法復原。請輸入管理密碼確認。</p>
        <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
        <label for="delete-password" class="form-label">管理密碼</label>
        <input id="delete-password" ref="passwordInput" v-model="password" type="password" autocomplete="off" maxlength="72" class="form-control" :disabled="pending" required :aria-invalid="!!error" />
        <div class="d-flex justify-content-end flex-wrap gap-2 mt-4">
          <button type="button" class="btn btn-outline-secondary touch-control" :disabled="pending" @click="close()">取消</button>
          <button type="submit" class="btn btn-danger touch-control" :disabled="pending"><span v-if="pending" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>{{ pending ? '正在刪除…' : '確認刪除' }}</button>
        </div>
      </form>
    </dialog>
  </Teleport>
</template>

<style scoped>
.delete-dialog { width: min(480px, calc(100% - 32px)); max-height: calc(100dvh - 32px); border: 0; border-radius: 16px; padding: 24px; color: #192f3a; overflow: auto; }
.delete-dialog::backdrop { background: #0b141bbd; }
.touch-control, .form-control { min-height: 44px; }
</style>
