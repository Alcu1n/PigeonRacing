<!-- [IN]: Published race id route param and member API / 已发布赛事 ID 路由参数与会员 API -->
<!-- [OUT]: Read-only mobile registration detail publication with searchable tabs / 支持搜索与标签切换的移动端只读报名明细发布页 -->
<!-- [POS]: Frontend race published details screen / 前端赛事已发布明细页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import MemberTopActions from '../components/MemberTopActions.vue'
import { api } from '../api/client'
import { registrationStatusText, registrationStatusTone, type PublishedRaceDetails, type PublishedRaceDetailsGroup } from '../types/domain'

type DetailTab = { key: string; label: string; kind: 'single' | 'multi' | 'progressive'; progressiveIndex?: number }

const route = useRoute()
const router = useRouter()
const details = ref<PublishedRaceDetails | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const activeTab = ref('')
const keyword = ref('')

const tabs = computed<DetailTab[]>(() => {
  const result: DetailTab[] = []
  if ((details.value?.single.projects.length ?? 0) > 0) result.push({ key: 'single', label: '单羽组', kind: 'single' })
  if ((details.value?.multi.length ?? 0) > 0) result.push({ key: 'multi', label: '多羽组', kind: 'multi' })
  details.value?.progressive.forEach((category, index) => {
    result.push({ key: `progressive:${category.category_id}`, label: category.category_name, kind: 'progressive', progressiveIndex: index })
  })

  return result
})

const active = computed(() => tabs.value.find((tab) => tab.key === activeTab.value) ?? tabs.value[0])
const normalizedKeyword = computed(() => keyword.value.trim().toLowerCase())

const filteredSingleRows = computed(() => {
  const rows = details.value?.single.rows ?? []
  if (!normalizedKeyword.value) return rows

  return rows.filter((row) => [row.loft_number, row.participant_name, row.ring_number].some((value) => value.toLowerCase().includes(normalizedKeyword.value)))
})

const filteredMulti = computed(() => {
  const projects = details.value?.multi ?? []
  return projects.map((project) => ({
    ...project,
    groups: filterGroups(project.groups),
  })).filter((project) => project.groups.length > 0)
})

const activeProgressive = computed(() => {
  if (!details.value || active.value?.kind !== 'progressive') return null
  const category = details.value.progressive[active.value.progressiveIndex ?? -1]
  if (!category) return null

  return {
    ...category,
    stages: category.stages.map((stage) => ({
      ...stage,
      groups: filterGroups(stage.groups),
    })).filter((stage) => stage.groups.length > 0),
  }
})

onMounted(async () => {
  try {
    const response = await api.get(`/api/member/races/${route.params.raceId}/published-details`)
    details.value = response.data
    activeTab.value = tabs.value[0]?.key ?? 'single'
  } catch {
    errorMessage.value = '报名明细尚未发布或加载失败'
  } finally {
    loading.value = false
  }
})

function filterGroups(groups: PublishedRaceDetailsGroup[]): PublishedRaceDetailsGroup[] {
  if (!normalizedKeyword.value) return groups

  return groups.filter((group) => [
    group.loft_number,
    group.participant_name,
    ...group.rings,
  ].some((value) => value.toLowerCase().includes(normalizedKeyword.value)))
}

function dateText(value?: string | null): string {
  return value?.slice(0, 16) ?? '-'
}
</script>

<template>
  <main class="page published-details-page">
    <header class="page-header page-topbar compact-detail-header">
      <div>
        <h1>报名明细</h1>
        <p>{{ details?.race.name || '赛事报名明细发布' }}</p>
      </div>
      <MemberTopActions />
    </header>

    <p v-if="loading" class="empty-note">加载报名明细中...</p>
    <section v-else-if="errorMessage" class="profile-card">
      <p class="empty-note">{{ errorMessage }}</p>
      <button class="secondary-action wide" @click="router.push('/races')">返回赛事列表</button>
    </section>

    <template v-else-if="details">
      <section class="published-summary-card">
        <div>
          <span>赛事</span>
          <strong>{{ details.race.name }}</strong>
        </div>
        <div>
          <span>报名截止</span>
          <strong>{{ dateText(details.race.registration_end_at) }}</strong>
        </div>
        <div>
          <span>发布范围</span>
          <strong>{{ details.scope_label }}</strong>
        </div>
      </section>

      <nav class="tabs published-tabs" aria-label="报名明细分类">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>

      <input v-model="keyword" class="published-search" placeholder="搜索棚号、参赛名、足环号" type="search" />

      <section v-if="active?.kind === 'single'" class="published-card">
        <div class="published-card-head">
          <h2>单羽组</h2>
          <span>{{ filteredSingleRows.length }} 条</span>
        </div>
        <p v-if="filteredSingleRows.length === 0" class="empty-note">暂无匹配的单羽明细</p>
        <div v-else class="published-table-wrap single-publication-wrap">
          <div class="published-single-grid published-header-row" :style="{ '--published-project-count': details.single.projects.length }">
            <div class="published-sticky-cell">棚号 / 参赛名 / 足环</div>
            <div v-for="project in details.single.projects" :key="project.id" class="published-project-head">{{ project.name }}</div>
          </div>
          <div
            v-for="row in filteredSingleRows"
            :key="`${row.loft_number}-${row.ring_number}`"
            class="published-single-grid published-body-row"
            :style="{ '--published-project-count': details.single.projects.length }"
          >
            <div class="published-sticky-cell identity-cell">
              <b>{{ row.loft_number }}</b>
              <span>{{ row.participant_name }}</span>
              <strong>{{ row.ring_number }}</strong>
            </div>
            <div v-for="project in details.single.projects" :key="project.id" class="published-check-cell">
              <span
                v-if="row.selected_projects[String(project.id)]"
                :class="['registration-status-pill', registrationStatusTone(row.selected_projects[String(project.id)])]"
              >
                {{ details.scope === 'all_submitted' ? registrationStatusText(row.selected_projects[String(project.id)]) : '★' }}
              </span>
              <span v-else class="empty-check">○</span>
            </div>
          </div>
        </div>
      </section>

      <section v-else-if="active?.kind === 'multi'" class="published-card">
        <div class="published-card-head">
          <h2>多羽组</h2>
          <span>{{ filteredMulti.reduce((sum, project) => sum + project.groups.length, 0) }} 组</span>
        </div>
        <p v-if="filteredMulti.length === 0" class="empty-note">暂无匹配的多羽组明细</p>
        <article v-for="project in filteredMulti" :key="project.project_id" class="published-group-block">
          <header>
            <strong>{{ project.project_name }}</strong>
            <span>{{ project.group_size }} 羽 / {{ project.groups.length }} 组</span>
          </header>
          <div class="published-group-table">
            <div class="published-group-row published-group-header">
              <b>棚号</b><b>参赛名</b><b>足环号码</b><b>状态</b>
            </div>
            <div v-for="group in project.groups" :key="`${group.loft_number}-${group.group_index}`" class="published-group-row">
              <span>{{ group.loft_number }}</span>
              <span>{{ group.participant_name }}</span>
              <span class="ring-lines">{{ group.rings.join('\n') }}</span>
              <span :class="['registration-status-pill', registrationStatusTone(group.status)]">{{ registrationStatusText(group.status) }}</span>
            </div>
          </div>
        </article>
      </section>

      <section v-else-if="active?.kind === 'progressive' && activeProgressive" class="published-card">
        <div class="published-card-head">
          <h2>{{ activeProgressive.category_name }}</h2>
          <span>{{ activeProgressive.stages.reduce((sum, stage) => sum + stage.groups.length, 0) }} 组</span>
        </div>
        <p v-if="activeProgressive.stages.length === 0" class="empty-note">暂无匹配的递进阶段明细</p>
        <article v-for="stage in activeProgressive.stages" :key="stage.stage_project_id" class="published-group-block">
          <header>
            <strong>{{ stage.stage_project_name }}</strong>
            <span>{{ stage.group_size }} 羽 / {{ stage.groups.length }} 组</span>
          </header>
          <div class="published-group-table">
            <div class="published-group-row published-group-header">
              <b>棚号</b><b>参赛名</b><b>足环号码</b><b>状态</b>
            </div>
            <div v-for="group in stage.groups" :key="`${group.loft_number}-${group.group_index}`" class="published-group-row">
              <span>{{ group.loft_number }}</span>
              <span>{{ group.participant_name }}</span>
              <span class="ring-lines">{{ group.rings.join('\n') }}</span>
              <span :class="['registration-status-pill', registrationStatusTone(group.status)]">{{ registrationStatusText(group.status) }}</span>
            </div>
          </div>
        </article>
      </section>

      <button class="secondary-action wide" @click="router.push('/races')">返回赛事列表</button>
    </template>
  </main>
</template>
