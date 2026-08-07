// [IN]: Integer cent amount / 整数分金额
// [OUT]: Human-readable CNY/TWD amount without exchange conversion / 不做汇率换算的人类可读 CNY/TWD 金额
// [POS]: Frontend money formatting helper / 前端金额格式化工具
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
export type CurrencyCode = 'CNY' | 'TWD'

export function formatMoney(cent: number, currency: CurrencyCode = 'CNY'): string {
  const symbol = currency === 'TWD' ? 'NT$' : '¥'
  const numberLocale = currency === 'TWD' ? 'zh-TW' : 'zh-CN'

  return `${symbol}${(cent / 100).toLocaleString(numberLocale, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`
}

export function yuan(cent: number): string {
  return formatMoney(cent, 'CNY')
}
