// [IN]: Public information helpers / 公开信息辅助函数
// [OUT]: Category label and sanitized HTML assertions / 分类标签与清洗 HTML 断言
// [POS]: Frontend information publishing unit tests / 前端信息发布单元测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { describe, expect, it } from 'vitest'
import { informationCategoryLabel, sanitizeInformationHtml } from '../utils/information'

describe('information helpers', () => {
  it('localizes fixed information categories', () => {
    expect(informationCategoryLabel('rules')).toBe('赛事规程')
    expect(informationCategoryLabel('results')).toBe('成绩发布')
    expect(informationCategoryLabel('notice')).toBe('通知公告')
  })

  it('removes unsafe scripts from rich text', () => {
    const html = sanitizeInformationHtml('<p><span class="color" data-color="red" style="--color: #dc2626">公告</span></p><script>alert(1)</script>')

    expect(html).toContain('data-color="red"')
    expect(html).toContain('公告')
    expect(html).not.toContain('<script>')
  })
})
