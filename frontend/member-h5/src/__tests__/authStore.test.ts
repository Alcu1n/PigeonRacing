// [IN]: Auth store and mocked member API client / 鉴权 Store 与模拟会员 API 客户端
// [OUT]: Login, profile restore, password-change, and registration reset assertions / 登录、档案恢复、改密与报名重置断言
// [POS]: Frontend auth store feature tests / 前端鉴权 Store 功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '../stores/auth'
import { useRegistrationStore } from '../stores/registration'
import { api } from '../api/client'

vi.mock('../api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  ensureCsrf: vi.fn(),
}))

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.mocked(api.get).mockReset()
    vi.mocked(api.post).mockReset()
  })

  it('keeps must-change-password state after login', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { member: memberPayload(true) },
    })

    const auth = useAuthStore()
    await auth.login('13800000000', 'password')

    expect(auth.member?.must_change_password).toBe(true)
  })

  it('clears registration runtime state before logging in', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { member: memberPayload(false) },
    })

    const auth = useAuthStore()
    const registration = useRegistrationStore()
    registration.bootstrap = {
      race: { id: 1, name: '旧赛事', registration_end_at: '2026-06-29 12:00:00', status: 'open', config_version: 1 },
      member: memberPayload(false),
      projects: [],
      pigeons: [],
      existing_registration: null,
    }

    await auth.login('13800000000', 'password')

    expect(registration.bootstrap).toBeNull()
  })

  it('clears member and registration runtime state after logout', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ data: { ok: true } })

    const auth = useAuthStore()
    const registration = useRegistrationStore()
    auth.member = memberPayload(false)
    registration.bootstrap = {
      race: { id: 1, name: '旧赛事', registration_end_at: '2026-06-29 12:00:00', status: 'open', config_version: 1 },
      member: memberPayload(false),
      projects: [],
      pigeons: [],
      existing_registration: null,
    }

    await auth.logout()

    expect(auth.member).toBeNull()
    expect(registration.bootstrap).toBeNull()
  })

  it('loads profile with pigeons when refreshing an authenticated route', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      data: { member: memberPayload(true), pigeons: [{ id: 1, ring_number: '2026-13-000001' }] },
    })

    const auth = useAuthStore()
    const profile = await auth.fetchProfile()

    expect(auth.member?.loft_number).toBe('A001')
    expect(profile.pigeons).toEqual([{ id: 1, ring_number: '2026-13-000001' }])
  })

  it('clears must-change-password state after password update', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      data: { member: memberPayload(false), pigeons: [] },
    })

    const auth = useAuthStore()
    auth.member = memberPayload(true)
    await auth.updatePassword('newpass', 'newpass')

    expect(auth.member?.must_change_password).toBe(false)
    expect(api.post).toHaveBeenCalledWith('/api/member/password', {
      password: 'newpass',
      password_confirmation: 'newpass',
    })
  })
})

function memberPayload(mustChangePassword: boolean) {
  return {
    id: 1,
    phone: '13800000000',
    loft_number: 'A001',
    participant_name: '张三鸽舍',
    must_change_password: mustChangePassword,
  }
}
