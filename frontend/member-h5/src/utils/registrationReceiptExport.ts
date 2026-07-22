// [IN]: Rendered receipt DOM, PNG blobs, and browser file capabilities / 已渲染凭证 DOM、PNG Blob 与浏览器文件能力
// [OUT]: Adaptive PNG rendering, direct download, mobile detection, and system sharing / 自适应 PNG 渲染、直接下载、移动端检测与系统分享
// [POS]: Registration receipt browser export boundary / 报名凭证浏览器导出边界
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import html2canvas from 'html2canvas'

const TARGET_SCALE = 2
const MAX_CANVAS_EDGE = 16_000
const MAX_CANVAS_PIXELS = 16_000_000

export type ReceiptShareResult = 'shared' | 'cancelled' | 'unsupported'

interface ReceiptShareBrowser {
  canShare?: (data?: ShareData) => boolean
  share?: (data?: ShareData) => Promise<void>
}

export function receiptCanvasScale(width: number, height: number): number {
  if (width <= 0 || height <= 0) return 1

  return Math.max(Number.EPSILON, Math.min(
    TARGET_SCALE,
    MAX_CANVAS_EDGE / width,
    MAX_CANVAS_EDGE / height,
    Math.sqrt(MAX_CANVAS_PIXELS / (width * height)),
  ))
}

export async function renderRegistrationReceipt(node: HTMLElement): Promise<Blob> {
  await document.fonts?.ready
  const width = Math.ceil(node.scrollWidth || node.getBoundingClientRect().width)
  const height = Math.ceil(node.scrollHeight || node.getBoundingClientRect().height)
  const preferredScale = receiptCanvasScale(width, height)
  const retryScale = preferredScale * 0.72
  let lastError: unknown

  for (const scale of [...new Set([preferredScale, retryScale])]) {
    try {
      const canvas = await html2canvas(node, {
        backgroundColor: '#ffffff',
        height,
        logging: false,
        scale,
        useCORS: true,
        width,
        windowHeight: height,
        windowWidth: width,
      })
      const blob = await canvasToPngBlob(canvas)
      if (blob) return blob
    } catch (error) {
      lastError = error
    }
  }

  throw lastError instanceof Error ? lastError : new Error('报名明细图片生成失败')
}

export function isMobileReceiptClient(userAgent = globalThis.navigator?.userAgent ?? ''): boolean {
  return /Android|iPhone|iPad|iPod|Mobile|MicroMessenger/i.test(userAgent)
}

export function isWechatClient(userAgent = globalThis.navigator?.userAgent ?? ''): boolean {
  return /MicroMessenger/i.test(userAgent)
}

export async function shareReceiptFile(
  blob: Blob,
  fileName: string,
  browser: ReceiptShareBrowser = globalThis.navigator ?? {},
): Promise<ReceiptShareResult> {
  const file = new File([blob], fileName, { type: 'image/png' })
  const shareData: ShareData = { files: [file], title: fileName }
  if (!browser.share || !browser.canShare?.({ files: [file] })) return 'unsupported'

  try {
    await browser.share(shareData)
    return 'shared'
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') return 'cancelled'
    throw error
  }
}

export function downloadReceiptBlob(blob: Blob, fileName: string): void {
  const objectUrl = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = fileName
  link.style.display = 'none'
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1_000)
}

function canvasToPngBlob(canvas: HTMLCanvasElement): Promise<Blob | null> {
  return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'))
}
