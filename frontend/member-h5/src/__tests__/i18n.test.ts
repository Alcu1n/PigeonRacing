// [IN]: Frontend locale resources, shared cookie, runtime config, and currency formatter / 前端语言资源、共享 Cookie、运行时配置与币种格式化器
// [OUT]: Key parity, manual locale persistence, runtime fallback, and CNY/TWD display assertions / Key 对齐、手动语言持久化、运行时回退与 CNY/TWD 显示断言
// [POS]: Frontend language and currency unit test / 前端语言与币种单元测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../api/client'
import { initializeLocale, locale, setLocale, t } from '../i18n'
import { zhCN, zhTW } from '../i18n/messages'
import { formatMoney } from '../utils/money'

describe('frontend locale and currency resources', () => {
  beforeEach(() => {
    setLocale('zh-CN')
    document.cookie = 'app_locale=; Max-Age=0; Path=/'
    vi.restoreAllMocks()
  })

  it('keeps simplified and Taiwan Traditional keys aligned', () => {
    expect(Object.keys(zhTW).sort()).toEqual(Object.keys(zhCN).sort())
    expect(t('登录')).toBe('登录')

    setLocale('zh-TW')
    expect(t('登录')).toBe('登入')
  })

  it('persists the manual locale in the shared root cookie', () => {
    setLocale('zh-TW')

    expect(document.cookie).toContain('app_locale=zh-TW')
    expect(locale.value).toBe('zh-TW')
  })

  it('uses the runtime locale when no manual cookie exists', async () => {
    vi.spyOn(api, 'get').mockResolvedValue({ data: { locale: 'zh-TW', source: 'ip' } } as never)

    await initializeLocale()

    expect(locale.value).toBe('zh-TW')
  })

  it('accepts the Laravel underscore cookie written by the admin switcher', async () => {
    document.cookie = 'app_locale=zh_TW; Max-Age=31536000; Path=/'

    await initializeLocale()

    expect(locale.value).toBe('zh-TW')
  })

  it('formats CNY and TWD without changing the cent value', () => {
    expect(formatMoney(12345, 'CNY')).toBe('¥123.45')
    expect(formatMoney(12345, 'TWD')).toBe('NT$123.45')
  })
})
