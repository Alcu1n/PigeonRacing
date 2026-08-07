<!-- [IN]: Authenticated member session, visible race API, member registration history API, and public information API / 已鉴权会员会话、可见赛事 API、会员报名历史 API 与公开信息 API -->
<!-- [OUT]: Modern compact member home with hero greeting, prominent race list with receipt download, sibling profile card, and embedded information feed / 现代化紧凑会员主页：问候 Hero、带报名明细下载的突出赛事列表、平级个人信息卡片与内嵌信息流 -->
<!-- [POS]: Frontend member home (race list) screen / 前端会员主页（赛事列表）页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import type { InformationCategory, InformationPostListItem, Race, RegistrationHistoryItem } from '../types/domain'
import { registrationStatusText, registrationStatusTone } from '../types/domain'
import { informationCategoryLabel } from '../utils/information'
import { useAuthStore } from '../stores/auth'
import MemberLogoutButton from '../components/MemberLogoutButton.vue'
import RegistrationReceiptDownload from '../components/RegistrationReceiptDownload.vue'
import { t } from '../i18n'

const router = useRouter()
const auth = useAuthStore()
const races = ref<Race[]>([])
const loading = ref(true)
const myRegistrations = ref<RegistrationHistoryItem[]>([])
const informationItems = ref<InformationPostListItem[]>([])
const informationLoading = ref(true)
const activeCategory = ref<InformationCategory | 'all'>('all')

const categories: Array<{ value: InformationCategory | 'all'; label: string }> = [
  { value: 'all', label: t('全部') },
  { value: 'rules', label: t('赛事规程') },
  { value: 'results', label: t('成绩发布') },
  { value: 'notice', label: t('通知公告') },
]

const openRaceCount = computed(() => races.value.filter((race) => raceListState(race) === 'open').length)
const visibleInformation = computed(() => informationItems.value.slice(0, 6))
const registrationsByRace = computed(() => {
  const map = new Map<number, RegistrationHistoryItem>()
  for (const item of myRegistrations.value) {
    map.set(item.race_id, item)
  }

  return map
})

onMounted(async () => {
  await Promise.all([loadRaces(), loadInformation(), loadMyRegistrations()])
})

async function loadRaces(): Promise<void> {
  try {
    const response = await api.get('/api/member/races')
    races.value = response.data
  } finally {
    loading.value = false
  }
}

async function loadMyRegistrations(): Promise<void> {
  try {
    const response = await api.get('/api/member/registrations')
    myRegistrations.value = response.data
  } catch {
    myRegistrations.value = []
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

function registrationFor(race: Race): RegistrationHistoryItem | undefined {
  return registrationsByRace.value.get(race.id)
}

function isRaceEnded(race: Race): boolean {
  const endTime = parseRaceEndAt(race.registration_end_at).getTime()

  return race.status !== 'open' || (Number.isFinite(endTime) && endTime <= Date.now())
}

type RaceListState = 'pending' | 'open' | 'ended'

function raceListState(race: Race): RaceListState {
  if (race.registration_state === 'pending' || race.registration_state === 'open' || race.registration_state === 'ended') {
    return race.registration_state
  }

  return isRaceEnded(race) ? 'ended' : 'open'
}

function raceStateText(state: RaceListState): string {
  return state === 'pending' ? t('未开始') : state === 'open' ? t('报名中') : t('报名已结束')
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
    <header class="member-hero">
      <div class="member-hero-top">
        <p class="member-hero-greeting">{{ t('你好，{name} · 棚号 {loft}', { name: auth.member?.participant_name || t('会员'), loft: auth.member?.loft_number || '-' }) }}</p>
        <MemberLogoutButton class="member-hero-logout" />
      </div>
      <h1 class="member-hero-heading">{{ t('今日赛事报名') }}</h1>
      <div class="member-hero-stats" :aria-label="t('赛事概览')">
        <span>
          <b>{{ openRaceCount }}</b>
          <small>{{ t('报名中') }}</small>
        </span>
        <span>
          <b>{{ races.length }}</b>
          <small>{{ t('可见赛事') }}</small>
        </span>
      </div>
    </header>

    <div class="race-home-grid">
      <section class="race-home-races" :aria-label="t('可报名赛事')">
        <div class="race-home-section-head featured">
          <h2>{{ t('可报名赛事') }}</h2>
          <span class="race-home-section-badge">{{ t('{count} 场', { count: races.length }) }}</span>
        </div>

        <p v-if="loading" class="empty-note">{{ t('加载赛事中...') }}</p>
        <p v-else-if="races.length === 0" class="race-home-empty">{{ t('暂无可报名赛事，请留意信息发布') }}</p>
        <section v-else class="race-list">
          <article v-for="race in races" :key="race.id" :class="['race-card', { ended: raceListState(race) === 'ended' }]">
            <div class="race-card-top">
              <span :class="['race-status-pill', raceListState(race)]">
                {{ raceStateText(raceListState(race)) }}
              </span>
              <span class="race-card-deadline">
                {{ t('截止 {date} {time}', { date: raceEndDate(race.registration_end_at), time: raceEndTime(race.registration_end_at) }) }}
              </span>
            </div>
            <h3>{{ race.name }}</h3>
            <div class="race-card-actions">
              <button
                :class="['race-entry-action', raceListState(race) === 'open' ? '' : raceListState(race)]"
                :disabled="raceListState(race) !== 'open'"
                @click="router.push(`/races/${race.id}/register`)"
              >
                {{ raceListState(race) === 'open' ? t('进入报名') : raceStateText(raceListState(race)) }}
              </button>
              <button
                v-if="race.has_published_details"
                class="race-detail-action"
                @click="router.push(`/races/${race.id}/details`)"
              >
                {{ t('全部明细') }}
              </button>
            </div>
            <div v-if="registrationFor(race)" class="race-card-registration">
              <span class="race-card-registration-meta">
                <b :class="['registration-status-pill', registrationStatusTone(registrationFor(race)!.status)]">
                  {{ t(registrationStatusText(registrationFor(race)!.status)) }}
                </b>
                <small>{{ t('报名号 {registrationNo}', { registrationNo: registrationFor(race)!.registration_no }) }}</small>
              </span>
              <RegistrationReceiptDownload compact :registration-id="registrationFor(race)!.registration_id" />
            </div>
          </article>
        </section>
      </section>

      <aside class="race-home-side">
        <section class="race-home-member-card" :aria-label="t('个人信息')">
          <div class="race-home-section-head">
            <h2>{{ t('个人信息') }}</h2>
          </div>
          <div class="race-home-member-identity">
            <span class="member-avatar">{{ (auth.member?.participant_name || t('会员')).slice(0, 1) }}</span>
            <div>
              <strong>{{ auth.member?.participant_name || t('会员') }}</strong>
              <small>{{ t('棚号') }} {{ auth.member?.loft_number || '-' }}</small>
            </div>
          </div>
          <dl class="member-rows">
            <div>
              <dt>{{ t('手机号') }}</dt>
              <dd>{{ auth.member?.phone || t('未设置') }}</dd>
            </div>
            <div>
              <dt>{{ t('账号状态') }}</dt>
              <dd>{{ t('正常') }}</dd>
            </div>
          </dl>
          <button class="race-home-profile-action" type="button" @click="router.push('/profile')">
            {{ t('查看个人信息') }}
          </button>
        </section>
      </aside>
    </div>

    <section class="race-home-info" :aria-label="t('信息发布')">
      <div class="race-home-section-head">
        <h2>{{ t('信息发布') }}</h2>
        <RouterLink class="race-home-info-more" to="/information">{{ t('查看全部') }}</RouterLink>
      </div>

      <nav class="race-home-info-tabs" :aria-label="t('信息分类')">
        <button
          v-for="category in categories"
          :key="category.value"
          :class="{ active: activeCategory === category.value }"
          @click="selectCategory(category.value)"
        >
          {{ category.label }}
        </button>
      </nav>

      <p v-if="informationLoading" class="empty-note">{{ t('加载信息中...') }}</p>
      <p v-else-if="visibleInformation.length === 0" class="race-home-empty">{{ t('暂无发布内容') }}</p>
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
            <em v-if="post.is_pinned">{{ t('置顶') }}</em>
          </span>
          <time>{{ formatTime(post.published_at) }}</time>
        </button>
      </div>
    </section>
  </main>
</template>
