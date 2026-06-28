<!-- [IN]: Branding API plus blank member phone and password input / 品牌 API 与空白会员手机号、密码输入 -->
<!-- [OUT]: Centered logo login screen with footer contact and authenticated race-list navigation / 带页脚联系信息的居中 Logo 登录页与已鉴权赛事列表导航 -->
<!-- [POS]: Frontend member login screen / 前端会员登录页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { showDialog, showToast } from 'vant'
import { useAuthStore } from '../stores/auth'
import { api } from '../api/client'

const auth = useAuthStore()
const router = useRouter()
const phone = ref('')
const password = ref('')
const loading = ref(false)
const logoUrl = ref<string | null>(null)

onMounted(async () => {
  try {
    const response = await api.get('/api/member/branding')
    logoUrl.value = response.data.logo_url ?? null
  } catch {
    logoUrl.value = null
  }
})

async function submit(): Promise<void> {
  loading.value = true
  try {
    await auth.login(phone.value, password.value)
    if (auth.member?.must_change_password) {
      await showDialog({
        title: '请先修改密码',
        message: '为了账号安全，首次登录需要先修改密码。',
        confirmButtonText: '去修改密码',
      })
      await router.replace('/profile?forcePassword=1')
      return
    }

    await router.push('/races')
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      showToast('手机号或密码错误')
      return
    }

    showToast('登录会话已失效，请刷新后重试')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login-screen">
    <section class="login-panel">
      <div class="login-brand">
        <img v-if="logoUrl" class="login-logo" :src="logoUrl" alt="飞乐赛鸽 Logo" @error="logoUrl = null" />
        <h1>赛鸽赛事报名</h1>
        <p>会员专属报名入口</p>
      </div>
      <label>
        <span>手机号</span>
        <input v-model="phone" inputmode="tel" autocomplete="username" placeholder="请输入会员手机号" />
      </label>
      <label>
        <span>密码</span>
        <input v-model="password" type="password" autocomplete="current-password" placeholder="请输入登录密码" />
      </label>
      <button class="primary-action wide" :disabled="loading" @click="submit">{{ loading ? '登录中...' : '登录' }}</button>
    </section>
    <footer class="login-footer">
      <span>© 飞乐赛鸽 2026 联系电话 18650024626</span>
      <span>定制开发 微信：lemonrere</span>
    </footer>
  </main>
</template>
