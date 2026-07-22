<!-- [IN]: Loaded registration or registration id, detail API, and browser export capabilities / 已加载报名或报名 ID、详情 API 与浏览器导出能力 -->
<!-- [OUT]: Reusable amber download action with desktop download, mobile preview/share, and WeChat original-image saving / 可复用琥珀金下载动作、电脑下载、手机预览分享与微信原图保存 -->
<!-- [POS]: Shared registration receipt download workflow / 共享报名凭证下载流程 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref } from 'vue'
import { Icon, showToast } from 'vant'
import { api } from '../api/client'
import type { ExistingRegistration } from '../types/domain'
import { buildRegistrationReceiptData, receiptFileName, type RegistrationReceiptData } from '../utils/registrationReceipt'
import {
  downloadReceiptBlob,
  isMobileReceiptClient,
  isWechatClient,
  receiptBlobDataUrl,
  renderRegistrationReceipt,
  shareReceiptFile,
} from '../utils/registrationReceiptExport'
import RegistrationReceipt from './RegistrationReceipt.vue'

const props = withDefaults(defineProps<{
  registration?: ExistingRegistration | null
  registrationId?: number
  compact?: boolean
}>(), {
  registration: null,
  registrationId: undefined,
  compact: false,
})

const generating = ref(false)
const saving = ref(false)
const triggerButton = ref<HTMLButtonElement | null>(null)
const previewDialog = ref<HTMLElement | null>(null)
const previewCloseButton = ref<HTMLButtonElement | null>(null)
const receipt = ref<RegistrationReceiptData | null>(null)
const renderHost = ref<HTMLElement | null>(null)
const previewBlob = ref<Blob | null>(null)
const previewUrl = ref('')
const previewObjectUrl = ref('')
const previewFileName = ref('')
const shareUnsupported = ref(false)

async function generateReceipt(): Promise<void> {
  if (generating.value) return

  generating.value = true
  try {
    const registration = props.registration ?? await loadRegistration()
    receipt.value = buildRegistrationReceiptData(registration)
    await nextTick()
    await waitForPaint()
    if (!renderHost.value) throw new Error('报名明细渲染区域不可用')

    const blob = await renderRegistrationReceipt(renderHost.value)
    const fileName = receiptFileName(receipt.value)
    if (isMobileReceiptClient()) {
      await openPreview(blob, fileName)
    } else {
      downloadReceiptBlob(blob, fileName)
      showToast('报名明细图片已下载')
    }
  } catch {
    showToast('报名明细图片生成失败，请重试')
  } finally {
    receipt.value = null
    generating.value = false
  }
}

async function saveToAlbum(): Promise<void> {
  if (!previewBlob.value || saving.value) return

  saving.value = true
  try {
    const result = await shareReceiptFile(previewBlob.value, previewFileName.value)
    if (result === 'shared') closePreview()
    if (result === 'unsupported') {
      shareUnsupported.value = true
      showToast('当前浏览器不支持直接保存，请长按图片保存')
    }
  } catch {
    showToast('系统分享打开失败，请长按图片保存')
  } finally {
    saving.value = false
  }
}

function downloadFallback(): void {
  if (!previewBlob.value) return
  downloadReceiptBlob(previewBlob.value, previewFileName.value)
}

async function loadRegistration(): Promise<ExistingRegistration> {
  if (!props.registrationId) throw new Error('缺少报名记录')
  const response = await api.get(`/api/member/registrations/${props.registrationId}`)

  return response.data
}

async function openPreview(blob: Blob, fileName: string): Promise<void> {
  revokePreviewUrl()
  const wechatClient = isWechatClient()
  const imageUrl = wechatClient
    ? await receiptBlobDataUrl(blob)
    : createPreviewObjectUrl(blob)

  previewBlob.value = blob
  previewFileName.value = fileName
  previewUrl.value = imageUrl
  shareUnsupported.value = wechatClient
  void nextTick(() => previewCloseButton.value?.focus())
}

function closePreview(): void {
  revokePreviewUrl()
  previewBlob.value = null
  previewFileName.value = ''
  shareUnsupported.value = false
  void nextTick(() => triggerButton.value?.focus())
}

function trapPreviewFocus(event: KeyboardEvent): void {
  const focusable = [...(previewDialog.value?.querySelectorAll<HTMLButtonElement>('button:not(:disabled)') ?? [])]
  const first = focusable[0]
  const last = focusable.at(-1)
  if (!first || !last) return

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

function revokePreviewUrl(): void {
  if (previewObjectUrl.value) URL.revokeObjectURL(previewObjectUrl.value)
  previewObjectUrl.value = ''
  previewUrl.value = ''
}

function createPreviewObjectUrl(blob: Blob): string {
  const objectUrl = URL.createObjectURL(blob)
  previewObjectUrl.value = objectUrl
  return objectUrl
}

function waitForPaint(): Promise<void> {
  return new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve())))
}

onBeforeUnmount(revokePreviewUrl)
</script>

<template>
  <button
    ref="triggerButton"
    type="button"
    :class="['receipt-download-action', { compact, wide: !compact }]"
    :disabled="generating"
    @click="generateReceipt"
  >
    <Icon name="down" class="receipt-download-icon" aria-hidden="true" />
    {{ generating ? '生成报名明细中…' : '下载报名明细' }}
  </button>

  <div v-if="receipt" class="receipt-render-stage" aria-hidden="true">
    <div ref="renderHost">
      <RegistrationReceipt :receipt="receipt" />
    </div>
  </div>

  <Teleport to="body">
    <div v-if="previewUrl" class="receipt-preview-mask" @click.self="closePreview" @keydown.esc.prevent.stop="closePreview" @keydown.tab="trapPreviewFocus">
      <section ref="previewDialog" class="receipt-preview-dialog" role="dialog" aria-modal="true" aria-label="报名明细图片预览">
        <header>
          <div>
            <strong>报名明细图片</strong>
            <span>{{ shareUnsupported ? '请长按图片保存' : '请核对后保存到相册' }}</span>
          </div>
          <button ref="previewCloseButton" type="button" aria-label="关闭预览" @click="closePreview">×</button>
        </header>
        <div class="receipt-preview-scroll">
          <img :src="previewUrl" alt="报名明细长图预览" />
        </div>
        <p v-if="shareUnsupported" class="receipt-save-hint">当前浏览器不支持直接写入相册，请长按上方原图选择保存，或下载 PNG。</p>
        <div :class="['receipt-preview-actions', { single: shareUnsupported }]">
          <button v-if="!shareUnsupported" type="button" class="receipt-save-action" :disabled="saving" @click="saveToAlbum">
            {{ saving ? '正在打开系统分享…' : '保存到相册' }}
          </button>
          <button type="button" class="receipt-fallback-action" @click="downloadFallback">下载 PNG</button>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.receipt-download-action {
  min-height: 46px;
  border: 1px solid #6f3d00;
  border-radius: 9px;
  padding: 0 18px;
  background: linear-gradient(180deg, #a96000, #794100);
  color: #fffaf0;
  font-size: 15px;
  font-weight: 950;
  text-shadow: 0 1px 0 rgba(67, 34, 0, .28);
  box-shadow: 0 10px 22px rgba(113, 61, 0, .22);
}

.receipt-download-icon {
  width: 24px;
  height: 24px;
  margin-right: 7px;
  border: 1px solid rgba(255, 250, 240, .38);
  border-radius: 50%;
  background: rgba(255, 250, 240, .14);
  color: inherit;
  font-size: 15px;
  line-height: 22px;
  text-align: center;
  vertical-align: -2px;
}

.receipt-download-action:active:not(:disabled) {
  transform: translateY(1px);
}

.receipt-download-action.wide {
  width: 100%;
  margin-top: 18px;
}

.receipt-download-action.compact {
  min-height: 34px;
  margin: 0;
  padding: 0 11px;
  border-radius: 7px;
  font-size: 12px;
  box-shadow: none;
}

.receipt-download-action.compact .receipt-download-icon {
  width: 19px;
  height: 19px;
  margin-right: 5px;
  font-size: 12px;
  line-height: 17px;
  vertical-align: -1px;
}

.receipt-render-stage {
  position: fixed;
  top: 0;
  left: -100000px;
  width: 750px;
  pointer-events: none;
}

.receipt-preview-mask {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: grid;
  place-items: center;
  padding: 14px;
  background: rgba(13, 25, 18, .68);
}

.receipt-preview-dialog {
  width: min(100%, 520px);
  max-height: min(90dvh, 820px);
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto auto;
  overflow: hidden;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 28px 70px rgba(0, 0, 0, .32);
}

.receipt-preview-dialog > header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 13px 15px;
  border-bottom: 1px solid #dbe5df;
}

.receipt-preview-dialog > header div {
  display: grid;
  gap: 2px;
}

.receipt-preview-dialog > header strong {
  color: #14201a;
  font-size: 16px;
}

.receipt-preview-dialog > header span {
  color: #65736b;
  font-size: 12px;
  font-weight: 700;
}

.receipt-preview-dialog > header button {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: #edf3ef;
  color: #355044;
  font-size: 23px;
  line-height: 1;
}

.receipt-preview-scroll {
  min-height: 180px;
  overflow: auto;
  padding: 12px;
  background: #e7e9e8;
}

.receipt-preview-scroll img {
  display: block;
  width: 100%;
  height: auto;
  background: #fff;
  box-shadow: 0 5px 16px rgba(0, 0, 0, .13);
  -webkit-user-select: auto;
  user-select: auto;
  -webkit-touch-callout: default;
}

.receipt-save-hint {
  margin: 0;
  padding: 10px 14px 0;
  color: #875300;
  font-size: 12px;
  font-weight: 800;
  line-height: 1.45;
}

.receipt-preview-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 9px;
  padding: 12px 14px 14px;
}

.receipt-preview-actions button {
  min-height: 44px;
  border-radius: 8px;
  font-weight: 900;
}

.receipt-save-action {
  background: #0b7a4b;
  color: #fff;
}

.receipt-fallback-action {
  border: 1px solid #bd7b05;
  background: #fff2cf;
  color: #875300;
}

.receipt-preview-actions.single {
  grid-template-columns: 1fr;
}
</style>
