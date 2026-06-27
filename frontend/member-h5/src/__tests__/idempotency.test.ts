// [IN]: Frontend idempotency key helper / 前端幂等键辅助函数
// [OUT]: UUID generation behavior assertions / UUID 生成行为断言
// [POS]: Frontend submit compatibility tests / 前端提交兼容性测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createIdempotencyKey } from '../utils/idempotency'

const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/

describe('createIdempotencyKey', () => {
  const originalCrypto = globalThis.crypto

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
    vi.stubGlobal('crypto', originalCrypto)
  })

  it('uses native randomUUID when available', () => {
    vi.stubGlobal('crypto', { randomUUID: () => '11111111-1111-4111-8111-111111111111' })

    expect(createIdempotencyKey()).toBe('11111111-1111-4111-8111-111111111111')
  })

  it('creates a valid UUID when randomUUID is unavailable', () => {
    vi.stubGlobal('crypto', {
      getRandomValues: (bytes: Uint8Array) => {
        bytes.set(Array.from({ length: bytes.length }, (_, index) => index + 1))
        return bytes
      },
    })

    expect(createIdempotencyKey()).toMatch(uuidPattern)
  })

  it('creates a valid UUID when browser crypto is unavailable', () => {
    vi.stubGlobal('crypto', undefined)
    vi.spyOn(Math, 'random').mockReturnValue(0.5)

    expect(createIdempotencyKey()).toMatch(uuidPattern)
  })
})
