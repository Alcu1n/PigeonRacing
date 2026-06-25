<!-- [IN]: Member auth store and router / 会员鉴权 Store 与路由 -->
<!-- [OUT]: Compact logout action for authenticated screens / 已登录页面的紧凑退出动作 -->
<!-- [POS]: Frontend shared member session control / 前端共享会员会话控件 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const loading = ref(false)

async function logout(): Promise<void> {
  if (loading.value) return

  loading.value = true
  try {
    await auth.logout()
    await router.replace('/login')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <button class="logout-action" type="button" :disabled="loading" @click="logout">
    {{ loading ? '退出中' : '退出' }}
  </button>
</template>
