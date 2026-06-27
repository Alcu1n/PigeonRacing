<!-- [IN]: Authenticated member session / 已鉴权会员会话 -->
<!-- [OUT]: Visible race cards, profile/logout actions, and registration entry navigation / 可见赛事卡片、个人信息/退出动作与报名入口导航 -->
<!-- [POS]: Frontend race list screen / 前端赛事列表页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import type { Race } from '../types/domain'
import MemberTopActions from '../components/MemberTopActions.vue'

const router = useRouter()
const races = ref<Race[]>([])
const loading = ref(true)

onMounted(async () => {
  const response = await api.get('/api/member/races')
  races.value = response.data
  loading.value = false
})
</script>

<template>
  <main class="page">
    <header class="page-header page-topbar">
      <div>
        <h1>可报名赛事</h1>
        <p>选择赛事进入报名</p>
      </div>
      <MemberTopActions />
    </header>

    <p v-if="loading" class="empty-note">加载赛事中...</p>
    <article v-for="race in races" :key="race.id" class="race-card">
      <div>
        <h2>{{ race.name }}</h2>
        <p>报名截止：{{ race.registration_end_at }}</p>
        <span :class="['status-dot', race.status]">{{ race.status === 'open' ? '报名中' : race.status }}</span>
      </div>
      <button class="primary-action" @click="router.push(`/races/${race.id}/register`)">进入报名</button>
    </article>
  </main>
</template>
