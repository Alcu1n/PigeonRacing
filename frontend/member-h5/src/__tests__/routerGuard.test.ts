// [IN]: Router and auth store state / 路由与鉴权 Store 状态
// [OUT]: First-login password-change and public information route guard assertions / 首次登录改密与公开信息路由守卫断言
// [POS]: Frontend route guard feature tests / 前端路由守卫功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { router } from '../router'
import { useAuthStore } from '../stores/auth'
import { api } from '../api/client'

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
    vi.clearAllMocks()
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

  it('allows unauthenticated visitors to open information pages', async () => {
    await router.push('/information')

    expect(router.currentRoute.value.path).toBe('/information')
    expect(api.get).not.toHaveBeenCalled()
  })

  it('redirects the typo information route to the canonical path', async () => {
    await router.push('/infomation')

    expect(router.currentRoute.value.path).toBe('/information')
  })
})
