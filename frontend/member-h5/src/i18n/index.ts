import { ref } from 'vue'
import { api } from '../api/client'
import { zhCN, zhTW, type MessageKey } from './messages'

export type FrontendLocale = 'zh-CN' | 'zh-TW'

export const locale = ref<FrontendLocale>('zh-CN')

function normalizeLocale(value: string | null | undefined): FrontendLocale | null {
  if (value === 'zh-CN' || value === 'zh_CN') return 'zh-CN'
  if (value === 'zh-TW' || value === 'zh_TW') return 'zh-TW'
  return null
}

function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null
  const pair = document.cookie.split('; ').find((item) => item.startsWith(`${name}=`))
  return pair ? decodeURIComponent(pair.slice(name.length + 1)) : null
}

export function setLocale(next: FrontendLocale): void {
  locale.value = next
  if (typeof document !== 'undefined') {
    document.cookie = `app_locale=${encodeURIComponent(next)}; Max-Age=31536000; Path=/; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`
  }
}

export function currentLocale(): FrontendLocale {
  return locale.value
}

export async function initializeLocale(): Promise<FrontendLocale> {
  const cookieLocale = readCookie('app_locale')
  const manualLocale = normalizeLocale(cookieLocale)
  if (manualLocale) {
    locale.value = manualLocale
    return manualLocale
  }

  try {
    const response = await api.get<{ locale?: string }>('/api/public/runtime-config')
    const runtimeLocale = normalizeLocale(response.data.locale)
    if (runtimeLocale) locale.value = runtimeLocale
  } catch {
    locale.value = 'zh-CN'
  }

  return locale.value
}

export function t(key: MessageKey | string, params: Record<string, string | number> = {}): string {
  const messages = locale.value === 'zh-TW' ? zhTW : zhCN
  let value = messages[key as MessageKey] ?? zhCN[key as MessageKey] ?? key

  Object.entries(params).forEach(([name, replacement]) => {
    value = value.replaceAll(`{${name}}`, String(replacement))
  })

  return value
}
