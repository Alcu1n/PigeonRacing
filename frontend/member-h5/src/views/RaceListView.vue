<!-- [IN]: Authenticated member session and visible race API / 已鉴权会员会话与可见赛事 API -->
<!-- [OUT]: Highlighted race list with deadline-aware actions, registration entry navigation, and published detail access / 带截止状态动作、报名入口与已发布明细入口的突出赛事列表 -->
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

function isRaceEnded(race: Race): boolean {
  const endTime = parseRaceEndAt(race.registration_end_at).getTime()

  return race.status !== 'open' || (Number.isFinite(endTime) && endTime <= Date.now())
}

function parseRaceEndAt(value: string): Date {
  return new Date(value.replace(' ', 'T'))
}

function raceEndDate(value: string): string {
  return value.split(' ')[0] ?? value
}

function raceEndTime(value: string): string {
  return value.split(' ')[1]?.slice(0, 5) ?? ''
}
</script>

<template>
  <main class="page race-list-page">
    <header class="page-header page-topbar">
      <div>
        <h1>可报名赛事</h1>
        <p>选择赛事进入报名</p>
      </div>
      <MemberTopActions />
    </header>

    <p v-if="loading" class="empty-note">加载赛事中...</p>
    <section class="race-list">
      <article v-for="race in races" :key="race.id" class="race-card">
        <h2>{{ race.name }}</h2>
        <div class="race-card-body">
          <div class="race-deadline-panel">
            <span>报名截止</span>
            <strong>{{ raceEndDate(race.registration_end_at) }}</strong>
            <small>{{ raceEndTime(race.registration_end_at) }}</small>
          </div>
          <div class="race-card-action">
            <span :class="['status-dot', isRaceEnded(race) ? 'ended' : 'open']">
              {{ isRaceEnded(race) ? '报名已结束' : '报名中' }}
            </span>
            <button
              :class="['primary-action', 'race-entry-action', { ended: isRaceEnded(race) }]"
              :disabled="isRaceEnded(race)"
              @click="router.push(`/races/${race.id}/register`)"
            >
              {{ isRaceEnded(race) ? '已结束' : '进入报名' }}
            </button>
            <button
              v-if="race.has_published_details"
              class="race-detail-action"
              @click="router.push(`/races/${race.id}/details`)"
            >
              报名明细
            </button>
          </div>
        </div>
      </article>
    </section>
  </main>
</template>
