// [IN]: Public information category values and rich HTML / 公开信息分类值与富文本 HTML
// [OUT]: Localized category labels and sanitized HTML / 本地化分类标签与清洗后的 HTML
// [POS]: Frontend information publishing helper / 前端信息发布辅助工具
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import DOMPurify from 'dompurify'
import type { InformationCategory } from '../types/domain'

export const informationCategoryLabels: Record<InformationCategory, string> = {
  rules: '赛事规程',
  results: '成绩发布',
  notice: '通知公告',
}

export function informationCategoryLabel(category: InformationCategory): string {
  return informationCategoryLabels[category] ?? category
}

export function sanitizeInformationHtml(html: string): string {
  return DOMPurify.sanitize(html, {
    USE_PROFILES: { html: true },
    ADD_ATTR: ['class', 'data-color', 'style'],
  })
}
