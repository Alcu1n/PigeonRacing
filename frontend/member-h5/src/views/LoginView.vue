<!-- [IN]: Member phone and password input / 会员手机号与密码输入 -->
<!-- [OUT]: Authenticated session and race-list navigation / 已鉴权会话与赛事列表导航 -->
<!-- [POS]: Frontend member login screen / 前端会员登录页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const phone = ref('13800000000')
const password = ref('password')
const loading = ref(false)

async function submit(): Promise<void> {
  loading.value = true
  try {
    await auth.login(phone.value, password.value)
    await router.push('/races')
  } catch {
    showToast('手机号或密码错误')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login-screen">
    <section class="login-panel">
      <h1>赛鸽赛事报名</h1>
      <p>会员手机端入口</p>
      <label>
        <span>手机号</span>
        <input v-model="phone" inputmode="tel" autocomplete="username" />
      </label>
      <label>
        <span>密码</span>
        <input v-model="password" type="password" autocomplete="current-password" />
      </label>
      <button class="primary-action wide" :disabled="loading" @click="submit">{{ loading ? '登录中...' : '登录' }}</button>
    </section>
  </main>
</template>
