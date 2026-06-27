// [IN]: Browser crypto capability / 浏览器加密随机能力
// [OUT]: RFC 4122 UUID idempotency keys / RFC 4122 UUID 幂等键
// [POS]: Frontend submit idempotency helper / 前端提交幂等辅助工具
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

export function createIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  const bytes = randomBytes(16)
  bytes[6] = (bytes[6] & 0x0f) | 0x40
  bytes[8] = (bytes[8] & 0x3f) | 0x80

  return [
    hex(bytes, 0, 4),
    hex(bytes, 4, 6),
    hex(bytes, 6, 8),
    hex(bytes, 8, 10),
    hex(bytes, 10, 16),
  ].join('-')
}

function randomBytes(length: number): Uint8Array {
  const bytes = new Uint8Array(length)

  if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
    crypto.getRandomValues(bytes)
    return bytes
  }

  for (let index = 0; index < bytes.length; index += 1) {
    bytes[index] = Math.floor(Math.random() * 256)
  }

  return bytes
}

function hex(bytes: Uint8Array, start: number, end: number): string {
  return Array.from(bytes.slice(start, end), (byte) => byte.toString(16).padStart(2, '0')).join('')
}
