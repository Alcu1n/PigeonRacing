<!-- [IN]: Authenticated member session, visible race API, and public information API / 已鉴权会员会话、可见赛事 API 与公开信息 API -->
<!-- [OUT]: Modern member home with hero greeting, prominent race list, sibling profile card, and embedded information feed / 现代化会员主页：问候 Hero、突出赛事列表、平级个人信息卡片与内嵌信息流 -->
<!-- [POS]: Frontend member home (race list) screen / 前端会员主页（赛事列表）页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import type { InformationCategory, InformationPostListItem, Race } from '../types/domain'
import { informationCategoryLabel } from '../utils/information'
import { useAuthStore } from '../stores/auth'
import MemberLogoutButton from '../components/MemberLogoutButton.vue'

const router = useRouter()
const auth = useAuthStore()
const races = ref<Race[]>([])
const loading = ref(true)
const informationItems = ref<InformationPostListItem[]>([])
const informationLoading = ref(true)
const activeCategory = ref<InformationCategory | 'all'>('all')

const categories: Array<{ value: InformationCategory | 'all'; label: string }> = [
  { value: 'all', label: '全部' },
  { value: 'rules', label: '赛事规程' },
  { value: 'results', label: '成绩发布' },
  { value: 'notice', label: '通知公告' },
]

const openRaceCount = computed(() => races.value.filter((race) => !isRaceEnded(race)).length)
const visibleInformation = computed(() => informationItems.value.slice(0, 6))

onMounted(async () => {
  await Promise.all([loadRaces(), loadInformation()])
})

async function loadRaces(): Promise<void> {
  try {
    const response = await api.get('/api/member/races')
    races.value = response.data
  } finally {
    loading.value = false
  }
}

async function loadInformation(): Promise<void> {
  informationLoading.value = true
  try {
    const params = activeCategory.value === 'all' ? {} : { category: activeCategory.value }
    const response = await api.get('/api/public/information', { params })
    informationItems.value = response.data.items ?? []
  } catch {
    informationItems.value = []
  } finally {
    informationLoading.value = false
  }
}

async function selectCategory(category: InformationCategory | 'all'): Promise<void> {
  if (activeCategory.value === category) return
  activeCategory.value = category
  await loadInformation()
}

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

function formatTime(value?: string | null): string {
  return value ? value.replace('T', ' ').slice(0, 16) : ''
}

function categoryClass(category: InformationCategory): string {
  return `information-list-category-${category}`
}
</script>

<template>
  <main class="page race-home">
    <header class="race-home-hero">
      <div class="race-home-hero-top">
        <span class="race-home-brand">赛鸽会员系统</span>
        <MemberLogoutButton class="race-home-logout" />
      </div>
      <div class="race-home-hero-title">
        <p>你好，{{ auth.member?.participant_name || '会员' }}</p>
        <h1>今日赛事报名</h1>
        <span>棚号 {{ auth.member?.loft_number || '-' }} · 选择赛事即可进入报名</span>
      </div>
      <div class="race-home-hero-stats" aria-label="赛事概览">
        <span>
          <b>{{ openRaceCount }}</b>
          <small>报名中赛事</small>
        </span>
        <span>
          <b>{{ races.length }}</b>
          <small>可见赛事</small>
        </span>
      </div>
    </header>

    <div class="race-home-grid">
      <section class="race-home-races" aria-label="可报名赛事">
        <div class="race-home-section-head">
          <h2>可报名赛事</h2>
          <span class="race-home-section-badge">{{ races.length }} 场</span>
        </div>

        <p v-if="loading" class="empty-note">加载赛事中...</p>
        <p v-else-if="races.length === 0" class="race-home-empty">暂无可报名赛事，请留意信息发布</p>
        <section v-else class="race-list">
          <article v-for="race in races" :key="race.id" :class="['race-card', { ended: isRaceEnded(race) }]">
            <div class="race-card-top">
              <span :class="['race-status-pill', isRaceEnded(race) ? 'ended' : 'open']">
                {{ isRaceEnded(race) ? '报名已结束' : '报名中' }}
              </span>
              <span class="race-card-deadline">
                截止 {{ raceEndDate(race.registration_end_at) }} {{ raceEndTime(race.registration_end_at) }}
              </span>
            </div>
            <h3>{{ race.name }}</h3>
            <div class="race-card-actions">
              <button
                :class="['race-entry-action', { ended: isRaceEnded(race) }]"
                :disabled="isRaceEnded(race)"
                @click="router.push(`/races/${race.id}/register`)"
              >
                {{ isRaceEnded(race) ? '报名已结束' : '进入报名' }}
              </button>
              <button
                v-if="race.has_published_details"
                class="race-detail-action"
                @click="router.push(`/races/${race.id}/details`)"
              >
                报名明细
              </button>
            </div>
          </article>
        </section>
      </section>

      <aside class="race-home-side">
        <section class="race-home-member-card" aria-label="个人信息">
          <div class="race-home-section-head">
            <h2>个人信息</h2>
          </div>
          <div class="race-home-member-identity">
            <span class="race-home-member-avatar">{{ (auth.member?.participant_name || '会').slice(0, 1) }}</span>
            <div>
              <strong>{{ auth.member?.participant_name || '会员' }}</strong>
              <small>棚号 {{ auth.member?.loft_number || '-' }}</small>
            </div>
          </div>
          <dl class="race-home-member-rows">
            <div>
              <dt>手机号</dt>
              <dd>{{ auth.member?.phone || '未设置' }}</dd>
            </div>
            <div>
              <dt>账号状态</dt>
              <dd>正常</dd>
            </div>
          </dl>
          <button class="race-home-profile-action" type="button" @click="router.push('/profile')">
            查看个人信息
          </button>
        </section>
      </aside>
    </div>

    <section class="race-home-info" aria-label="信息发布">
      <div class="race-home-section-head">
        <h2>信息发布</h2>
        <RouterLink class="race-home-info-more" to="/information">查看全部</RouterLink>
      </div>

      <nav class="race-home-info-tabs" aria-label="信息分类">
        <button
          v-for="category in categories"
          :key="category.value"
          :class="{ active: activeCategory === category.value }"
          @click="selectCategory(category.value)"
        >
          {{ category.label }}
        </button>
      </nav>

      <p v-if="informationLoading" class="empty-note">加载信息中...</p>
      <p v-else-if="visibleInformation.length === 0" class="race-home-empty">暂无发布内容</p>
      <div v-else class="race-home-info-list">
        <button
          v-for="post in visibleInformation"
          :key="post.id"
          class="race-home-info-row"
          @click="router.push(`/information/${post.slug}`)"
        >
          <b :class="categoryClass(post.category)">{{ informationCategoryLabel(post.category) }}</b>
          <span class="race-home-info-title">
            {{ post.title }}
            <em v-if="post.is_pinned">置顶</em>
          </span>
          <time>{{ formatTime(post.published_at) }}</time>
        </button>
      </div>
    </section>
  </main>
</template>
