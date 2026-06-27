// [IN]: Router and auth store state / 路由与鉴权 Store 状态
// [OUT]: First-login password-change route guard assertions / 首次登录改密路由守卫断言
// [POS]: Frontend route guard feature tests / 前端路由守卫功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { router } from '../router'
import { useAuthStore } from '../stores/auth'

vi.mock('../api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  ensureCsrf: vi.fn(),
}))

describe('first-login password route guard', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    await router.replace('/login')
  })

  it('redirects password-change-required members away from race list', async () => {
    const auth = useAuthStore()
    auth.member = {
      id: 1,
      phone: '13800000000',
      loft_number: 'A001',
      participant_name: '张三鸽舍',
      must_change_password: true,
    }

    await router.push('/races')

    expect(router.currentRoute.value.path).toBe('/profile')
    expect(router.currentRoute.value.query.forcePassword).toBe('1')
  })
})
