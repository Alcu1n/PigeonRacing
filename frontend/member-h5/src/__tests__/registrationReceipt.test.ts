// [IN]: Receipt export compatibility helpers / 凭证导出兼容辅助函数
// [OUT]: Filename, adaptive scale, and file-share behavior assertions / 文件名、自适应倍率与文件分享行为断言
// [POS]: Frontend registration receipt export tests / 前端报名凭证导出测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { describe, expect, it, vi } from 'vitest'
import { receiptFileName } from '../utils/registrationReceipt'
import {
  downloadReceiptBlob,
  isMobileReceiptClient,
  isWechatClient,
  receiptBlobDataUrl,
  receiptCanvasScale,
  renderRegistrationReceipt,
  shareReceiptFile,
} from '../utils/registrationReceiptExport'

const { html2canvasMock } = vi.hoisted(() => ({ html2canvasMock: vi.fn() }))

vi.mock('html2canvas', () => ({ default: html2canvasMock }))

describe('registration receipt export', () => {
  it('sanitizes receipt filenames for browser downloads', () => {
    expect(receiptFileName({ race_name: '春季/大奖赛:*', registration_no: 'R4/0001' }))
      .toBe('春季-大奖赛---报名明细-R4-0001.png')
  })

  it('keeps short receipts sharp and lowers scale for long receipts', () => {
    expect(receiptCanvasScale(750, 4000)).toBe(2)
    expect(receiptCanvasScale(750, 10000)).toBeCloseTo(1.46, 2)
    expect(receiptCanvasScale(750, 20000)).toBeCloseTo(0.8, 2)
    expect(receiptCanvasScale(750, 100000)).toBeCloseTo(0.16, 2)
  })

  it('retries long receipt rendering once at a lower scale', async () => {
    const node = document.createElement('div')
    Object.defineProperties(node, {
      scrollWidth: { value: 750 },
      scrollHeight: { value: 20_000 },
    })
    const canvas = document.createElement('canvas')
    vi.spyOn(canvas, 'toBlob').mockImplementation((callback) => callback(new Blob(['png'], { type: 'image/png' })))
    html2canvasMock.mockRejectedValueOnce(new Error('canvas allocation failed')).mockResolvedValueOnce(canvas)

    await expect(renderRegistrationReceipt(node)).resolves.toBeInstanceOf(Blob)

    expect(html2canvasMock).toHaveBeenCalledTimes(2)
    expect(html2canvasMock.mock.calls[1][1].scale).toBeLessThan(html2canvasMock.mock.calls[0][1].scale)
  })

  it('returns unsupported when file sharing is unavailable', async () => {
    const result = await shareReceiptFile(new Blob(['png'], { type: 'image/png' }), 'receipt.png', {})

    expect(result).toBe('unsupported')
  })

  it('shares a PNG file when the browser supports file sharing', async () => {
    const browser = {
      canShare: vi.fn(() => true),
      share: vi.fn().mockResolvedValue(undefined),
    }

    await expect(shareReceiptFile(new Blob(['png'], { type: 'image/png' }), 'receipt.png', browser)).resolves.toBe('shared')
    expect(browser.share).toHaveBeenCalledWith(expect.objectContaining({ files: [expect.any(File)] }))
  })

  it('surfaces real system share failures', async () => {
    const browser = {
      canShare: vi.fn(() => true),
      share: vi.fn().mockRejectedValue(new Error('share failed')),
    }

    await expect(shareReceiptFile(new Blob(['png'], { type: 'image/png' }), 'receipt.png', browser)).rejects.toThrow('share failed')
  })

  it('recognizes mobile and WeChat clients for the preview fallback', () => {
    expect(isMobileReceiptClient('Mozilla/5.0 (iPhone) Mobile')).toBe(true)
    expect(isWechatClient('Mozilla/5.0 MicroMessenger/8.0')).toBe(true)
    expect(isMobileReceiptClient('Mozilla/5.0 Macintosh Chrome')).toBe(false)
  })

  it('turns the clean PNG blob into a data URL for WeChat long press', async () => {
    await expect(receiptBlobDataUrl(new Blob(['png'], { type: 'image/png' })))
      .resolves.toMatch(/^data:image\/png;base64,/)
  })

  it('treats cancelling the system share sheet as a normal outcome', async () => {
    const browser = {
      canShare: vi.fn(() => true),
      share: vi.fn().mockRejectedValue(new DOMException('cancelled', 'AbortError')),
    }

    const result = await shareReceiptFile(new Blob(['png'], { type: 'image/png' }), 'receipt.png', browser)

    expect(result).toBe('cancelled')
  })

  it('releases the temporary object URL after a desktop download', () => {
    vi.useFakeTimers()
    const createObjectUrl = vi.fn(() => 'blob:receipt')
    const revokeObjectUrl = vi.fn()
    vi.stubGlobal('URL', { createObjectURL: createObjectUrl, revokeObjectURL: revokeObjectUrl })
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)

    downloadReceiptBlob(new Blob(['png'], { type: 'image/png' }), 'receipt.png')
    vi.runAllTimers()

    expect(createObjectUrl).toHaveBeenCalledOnce()
    expect(click).toHaveBeenCalledOnce()
    expect(revokeObjectUrl).toHaveBeenCalledWith('blob:receipt')
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })
})
