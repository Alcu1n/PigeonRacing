// [IN]: Registration history matrix helper and route guard / 报名历史矩阵辅助函数与路由守卫
// [OUT]: History detail matrix and forced-password route assertions / 历史详情矩阵与强制改密路由断言
// [POS]: Frontend registration history feature tests / 前端报名历史功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { router } from '../router'
import { useAuthStore } from '../stores/auth'
import { buildRegistrationHistoryMatrix } from '../utils/registrationHistory'
import type { ExistingRegistration } from '../types/domain'

vi.mock('../api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  ensureCsrf: vi.fn(),
}))

describe('registration history', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    await router.replace('/login')
  })

  it('builds a ring-first single matrix and grouped multi details', () => {
    const matrix = buildRegistrationHistoryMatrix(registration())

    expect(matrix.single.projects.map((project) => project.name)).toEqual(['单羽 50', '单羽 100'])
    expect(matrix.single.rows).toEqual([
      {
        ring_number: '2026-13-000001',
        selected_project_ids: { 1: true, 2: true },
        count: 2,
        amount_cent: 15000,
      },
    ])
    expect(matrix.multi[0]).toMatchObject({
      project_name: '双羽组 200',
      group_count: 1,
      amount_cent: 20000,
    })
    expect(matrix.multi[0].groups[0].rings).toEqual(['2026-13-000001', '2026-13-000002'])
  })

  it('keeps forced-password members away from history detail', async () => {
    const auth = useAuthStore()
    auth.member = {
      id: 1,
      phone: '13800000000',
      loft_number: 'A001',
      participant_name: '张三鸽舍',
      must_change_password: true,
    }

    await router.push('/profile/registrations/1')

    expect(router.currentRoute.value.path).toBe('/profile')
    expect(router.currentRoute.value.query.forcePassword).toBe('1')
  })
})

function registration(): ExistingRegistration {
  return {
    id: 1,
    registration_no: 'REG001',
    status: 'submitted',
    total_amount_cent: 35000,
    submitted_at: '2026-06-27 10:00:00',
    entries: [
      {
        project_id: 1,
        project_name: '单羽 50',
        group_size: 1,
        price_cent: 5000,
        group_index: 1,
        pigeons: [{ pigeon_id: 1, ring_number: '2026-13-000001', sort_order: 1 }],
      },
      {
        project_id: 2,
        project_name: '单羽 100',
        group_size: 1,
        price_cent: 10000,
        group_index: 1,
        pigeons: [{ pigeon_id: 1, ring_number: '2026-13-000001', sort_order: 1 }],
      },
      {
        project_id: 3,
        project_name: '双羽组 200',
        group_size: 2,
        price_cent: 20000,
        group_index: 1,
        pigeons: [
          { pigeon_id: 1, ring_number: '2026-13-000001', sort_order: 1 },
          { pigeon_id: 2, ring_number: '2026-13-000002', sort_order: 2 },
        ],
      },
    ],
  }
}
