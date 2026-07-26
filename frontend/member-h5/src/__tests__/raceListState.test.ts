// [IN]: Race list view with backend registration_state payloads / 带后端 registration_state 的赛事列表视图
// [OUT]: Pending, open, and ended race pill and entry-button assertions / 未开始、报名中、已结束赛事徽标与报名按钮断言
// [POS]: Frontend race list registration state tests / 前端赛事列表报名状态测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, mount } from '@vue/test-utils'
import { router } from '../router'
import { useAuthStore } from '../stores/auth'
import RaceListView from '../views/RaceListView.vue'
import { api } from '../api/client'

vi.mock('../api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  ensureCsrf: vi.fn(),
}))

describe('race list registration state', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    const auth = useAuthStore()
    auth.member = {
      id: 1,
      phone: '13800000000',
      loft_number: 'A001',
      participant_name: '张三鸽舍',
      must_change_password: false,
    }
    await router.replace('/races')
  })

  it('shows 未开始 for future races and only enables entry while open', async () => {
    vi.mocked(api.get).mockImplementation((url) => {
      if (url === '/api/member/races') {
        return Promise.resolve({
          data: [
            { id: 1, name: '未来赛事', registration_end_at: '2099-07-01 12:00:00', status: 'published', has_published_details: false, registration_state: 'pending' },
            { id: 2, name: '报名中赛事', registration_end_at: '2099-07-01 12:00:00', status: 'open', has_published_details: false, registration_state: 'open' },
            { id: 3, name: '已结束赛事', registration_end_at: '2020-07-01 12:00:00', status: 'published', has_published_details: false, registration_state: 'ended' },
          ],
        })
      }
      if (url === '/api/member/registrations') return Promise.resolve({ data: [] })

      return Promise.resolve({ data: { items: [] } })
    })

    const wrapper = mount(RaceListView, { global: { plugins: [router] } })
    await flushPromises()

    const pills = wrapper.findAll('.race-status-pill').map((pill) => pill.text())
    expect(pills).toEqual(['未开始', '报名中', '报名已结束'])

    const buttons = wrapper.findAll('.race-entry-action')
    expect(buttons).toHaveLength(3)
    expect(buttons[0].text()).toBe('未开始')
    expect(buttons[0].attributes('disabled')).toBeDefined()
    expect(buttons[1].text()).toBe('进入报名')
    expect(buttons[1].attributes('disabled')).toBeUndefined()
    expect(buttons[2].text()).toBe('报名已结束')
    expect(buttons[2].attributes('disabled')).toBeDefined()
  })
})
