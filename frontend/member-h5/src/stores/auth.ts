// [IN]: Member credentials and backend auth API / 会员凭据与后端鉴权 API
// [OUT]: Current member session state and local logout reset / 当前会员会话状态与本地退出重置
// [POS]: Frontend member auth store / 前端会员鉴权状态
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api, ensureCsrf } from '../api/client'
import type { Member } from '../types/domain'

export const useAuthStore = defineStore('auth', () => {
  const member = ref<Member | null>(null)

  async function login(phone: string, password: string): Promise<void> {
    await ensureCsrf()
    const response = await api.post('/api/member/login', { phone, password })
    member.value = response.data.member
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/api/member/logout')
    } finally {
      member.value = null
    }
  }

  return { member, login, logout }
})
