// [IN]: Race list and published detail Vue views / 赛事列表与已发布明细 Vue 页面
// [OUT]: Published detail button, tab rendering, and search assertions / 已发布明细按钮、标签渲染与搜索断言
// [POS]: Frontend published race details feature tests / 前端赛事已发布明细功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises, mount } from '@vue/test-utils'
import { router } from '../router'
import { useAuthStore } from '../stores/auth'
import RaceListView from '../views/RaceListView.vue'
import RacePublishedDetailsView from '../views/RacePublishedDetailsView.vue'
import { api } from '../api/client'

vi.mock('../api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  ensureCsrf: vi.fn(),
}))

describe('published race details', () => {
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

  it('shows the detail button only for races with published details', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      data: [
        { id: 1, name: '已发布赛事', registration_end_at: '2026-07-01 12:00:00', status: 'closed', has_published_details: true },
        { id: 2, name: '未发布赛事', registration_end_at: '2026-07-01 12:00:00', status: 'closed', has_published_details: false },
      ],
    })

    const wrapper = mount(RaceListView, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('已发布赛事')
    expect(wrapper.findAll('button').filter((button) => button.text() === '报名明细')).toHaveLength(1)
  })

  it('renders searchable single, multi, and progressive sections', async () => {
    await router.replace('/races/1/details')
    vi.mocked(api.get).mockResolvedValueOnce({ data: publishedDetails() })

    const wrapper = mount(RacePublishedDetailsView, { global: { plugins: [router] } })
    await flushPromises()

    expect(wrapper.text()).toContain('测试赛事')
    expect(wrapper.text()).toContain('单羽组')
    expect(wrapper.text()).toContain('多羽组')
    expect(wrapper.text()).toContain('站站赛')
    expect(wrapper.text()).toContain('2026-13-000001')

    await wrapper.find('input[type="search"]').setValue('A002')

    expect(wrapper.text()).not.toContain('2026-13-000001')
  })
})

function publishedDetails() {
  return {
    race: { id: 1, name: '测试赛事', registration_end_at: '2026-07-01 12:00:00' },
    published_at: '2026-07-02 12:00:00',
    scope: 'all_submitted',
    scope_label: '全部提交',
    single: {
      projects: [{ id: 1, name: '单羽 50', sort_order: 1 }],
      rows: [
        {
          loft_number: 'A001',
          participant_name: '张三鸽舍',
          ring_number: '2026-13-000001',
          selected_projects: { 1: 'confirmed' },
        },
      ],
    },
    multi: [
      {
        project_id: 2,
        project_name: '双羽组',
        group_size: 2,
        groups: [
          {
            loft_number: 'A001',
            participant_name: '张三鸽舍',
            group_index: 1,
            status: 'confirmed',
            rings: ['2026-13-000001', '2026-13-000002'],
          },
        ],
      },
    ],
    progressive: [
      {
        category_id: 1,
        category_name: '站站赛',
        stages: [
          {
            stage_project_id: 3,
            stage_project_name: '第一阶段',
            group_size: 1,
            groups: [
              {
                loft_number: 'A001',
                participant_name: '张三鸽舍',
                group_index: 1,
                status: 'confirmed',
                rings: ['2026-13-000001'],
              },
            ],
          },
        ],
      },
    ],
  }
}
