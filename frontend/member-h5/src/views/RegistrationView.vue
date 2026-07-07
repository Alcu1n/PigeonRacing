<!-- [IN]: Race id route param and backend bootstrap API / 赛事 ID 路由参数与后端初始化 API -->
<!-- [OUT]: Ultra-compact race header with dynamic standard, progressive, detail, and submit workflow / 带动态普通、递进、明细与提交流程的超紧凑赛事头部 -->
<!-- [POS]: Frontend core registration screen / 前端核心报名页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showDialog, showToast } from 'vant'
import { api } from '../api/client'
import { useRegistrationStore } from '../stores/registration'
import { createIdempotencyKey } from '../utils/idempotency'
import { yuan } from '../utils/money'
import { demoBootstrap } from '../utils/demoBootstrap'
import AmountBar from '../components/AmountBar.vue'
import SingleMatrix from '../components/SingleMatrix.vue'
import MultiGroupBuilder from '../components/MultiGroupBuilder.vue'
import ProgressiveStageMatrix from '../components/ProgressiveStageMatrix.vue'
import SelectedDetail from '../components/SelectedDetail.vue'
import MemberTopActions from '../components/MemberTopActions.vue'

const route = useRoute()
const router = useRouter()
const store = useRegistrationStore()
const loading = ref(true)
const submitting = ref(false)

onMounted(async () => {
  try {
    const response = await api.get(`/api/member/races/${route.params.raceId}/bootstrap`)
    store.load(response.data)
  } catch (error) {
    if (!import.meta.env.DEV) throw error
    store.load(demoBootstrap())
    showToast('后端未运行，已加载演示数据')
  }
  loading.value = false
})

async function confirmSubmit(): Promise<void> {
  if (!store.race || (store.submitEntries.length === 0 && store.submitProgressiveEntries.length === 0)) return
  await showDialog({
    title: '确认提交报名',
    message: `共 ${store.selectedCount} 项，总金额 ${yuan(store.totalAmountCent)}。提交后以后台校验金额为准。`,
    showCancelButton: true,
    confirmButtonText: '确认提交',
  })

  submitting.value = true
  try {
    const response = await api.post(`/api/member/races/${store.race.id}/registrations`, {
      config_version: store.race.config_version,
      idempotency_key: createIdempotencyKey(),
      entries: store.submitEntries,
      progressive_entries: store.submitProgressiveEntries,
    })
    showToast('报名提交成功')
    await router.push(`/registrations/${response.data.id}`)
  } catch (error: any) {
    showToast(error?.response?.data?.message ?? '提交失败')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="registration-page">
    <p v-if="loading" class="empty-note">加载报名数据中...</p>
    <template v-else-if="store.race && store.member">
      <header class="registration-header">
        <div class="registration-title-row">
          <div class="registration-header-main">
            <h1>{{ store.race.name }}</h1>
            <div class="member-line">
              <span>{{ store.member.loft_number }}</span>
              <span>{{ store.member.participant_name }}</span>
              <span>{{ store.pigeons.length }} 羽</span>
            </div>
            <p class="deadline-line">报名截止：{{ store.race.registration_end_at }}</p>
          </div>
          <MemberTopActions show-race-list-return />
        </div>
      </header>

      <nav class="tabs">
        <button v-if="store.singleProjects.length" :class="{ active: store.activeTab === 'single' }" @click="store.activeTab = 'single'">单羽组</button>
        <button v-if="store.multiProjects.length" :class="{ active: store.activeTab === 'multi' }" @click="store.activeTab = 'multi'">多羽组</button>
        <button
          v-for="category in store.progressiveCategories"
          :key="category.id"
          :class="{ active: store.activeTab === store.progressiveTabId(category.id) }"
          @click="store.activeTab = store.progressiveTabId(category.id)"
        >
          {{ category.name }}
        </button>
        <button :class="{ active: store.activeTab === 'detail' }" @click="store.activeTab = 'detail'">已选明细</button>
      </nav>

      <div class="search-box">
        <input v-model="store.searchQuery" aria-label="搜索足环号码" placeholder="搜索足环号 / 尾号" />
      </div>

      <SingleMatrix v-if="store.activeTab === 'single'" />
      <MultiGroupBuilder v-else-if="store.activeTab === 'multi'" />
      <ProgressiveStageMatrix v-else-if="store.activeProgressiveCategory" />
      <SelectedDetail v-else />

      <AmountBar
        :selected-count="store.selectedCount"
        :total-amount-cent="store.totalAmountCent"
        @submit="confirmSubmit"
      />
      <div v-if="submitting" class="submit-mask">提交中，请勿重复点击</div>
    </template>
  </main>
</template>
