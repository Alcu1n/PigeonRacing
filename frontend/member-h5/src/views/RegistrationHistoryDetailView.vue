<!-- [IN]: Registration history route param and backend detail API / 报名历史路由参数与后端详情 API -->
<!-- [OUT]: Read-only mobile-friendly registration history detail / 移动端友好的只读报名历史详情 -->
<!-- [POS]: Frontend member registration history detail screen / 前端会员报名历史详情页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import MemberTopActions from '../components/MemberTopActions.vue'
import { api } from '../api/client'
import type { ExistingRegistration } from '../types/domain'
import { buildRegistrationHistoryMatrix } from '../utils/registrationHistory'
import { yuan } from '../utils/money'

const route = useRoute()
const router = useRouter()
const registration = ref<ExistingRegistration | null>(null)
const loading = ref(true)
const errorMessage = ref('')
const matrix = computed(() => registration.value ? buildRegistrationHistoryMatrix(registration.value) : null)

onMounted(async () => {
  try {
    const response = await api.get(`/api/member/registrations/${route.params.registrationId}`)
    registration.value = response.data
  } catch {
    errorMessage.value = '报名明细加载失败'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="page profile-page">
    <header class="page-header page-topbar">
      <div>
        <h1>报名明细</h1>
        <p>历史报名记录只读查看</p>
      </div>
      <MemberTopActions />
    </header>

    <p v-if="loading" class="empty-note">加载报名明细中...</p>
    <section v-else-if="errorMessage" class="profile-card">
      <p class="empty-note">{{ errorMessage }}</p>
      <button class="secondary-action wide" @click="router.push('/profile')">返回个人信息</button>
    </section>
    <template v-else-if="registration && matrix">
      <section class="profile-card history-summary-card">
        <dl>
          <dt>赛事名称</dt>
          <dd>{{ registration.race_name || '未知赛事' }}</dd>
          <dt>报名编号</dt>
          <dd>{{ registration.registration_no }}</dd>
          <dt>提交时间</dt>
          <dd>{{ registration.submitted_at }}</dd>
          <dt>总金额</dt>
          <dd>{{ yuan(registration.total_amount_cent) }}</dd>
          <dt>状态</dt>
          <dd>{{ registration.status }}</dd>
        </dl>
      </section>

      <section class="profile-card history-card">
        <div class="history-card-head">
          <h2>单羽组</h2>
          <span>{{ matrix.single.total_count }} 项 / {{ yuan(matrix.single.total_amount_cent) }}</span>
        </div>
        <p v-if="matrix.single.rows.length === 0" class="empty-note">暂无单羽组报名</p>
        <div v-else class="history-matrix-wrap">
          <div class="history-matrix-grid history-header-row" :style="{ '--history-project-count': matrix.single.projects.length }">
            <div class="history-ring-cell sticky-history-ring-cell">足环号</div>
            <div v-for="project in matrix.single.projects" :key="project.id" class="history-project-head">{{ project.name }}</div>
          </div>
          <div
            v-for="row in matrix.single.rows"
            :key="row.ring_number"
            class="history-matrix-grid history-body-row"
            :style="{ '--history-project-count': matrix.single.projects.length }"
          >
            <div class="history-ring-cell sticky-history-ring-cell">{{ row.ring_number }}</div>
            <div v-for="project in matrix.single.projects" :key="project.id" class="history-check-cell">
              <span v-if="row.selected_project_ids[project.id]">✅</span>
              <span v-else>○</span>
            </div>
          </div>
        </div>
      </section>

      <section class="profile-card history-card">
        <div class="history-card-head">
          <h2>多羽组</h2>
          <span>{{ matrix.multi.reduce((sum, project) => sum + project.group_count, 0) }} 组</span>
        </div>
        <p v-if="matrix.multi.length === 0" class="empty-note">暂无多羽组报名</p>
        <article v-for="project in matrix.multi" :key="project.project_id" class="history-multi-block">
          <header>
            <strong>{{ project.project_name }}</strong>
            <span>{{ project.group_count }} 组 / {{ yuan(project.amount_cent) }}</span>
          </header>
          <div v-for="group in project.groups" :key="group.group_index" class="history-group-row">
            <b>第 {{ group.group_index }} 组</b>
            <span>{{ group.rings.join(' / ') }}</span>
          </div>
        </article>
      </section>

      <button class="secondary-action wide" @click="router.push('/profile')">返回个人信息</button>
    </template>
  </main>
</template>
