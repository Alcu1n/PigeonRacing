<!-- [IN]: Race id route param and backend bootstrap API / 赛事 ID 路由参数与后端初始化 API -->
<!-- [OUT]: Local single, multi, detail, and submit workflow / 本地单羽、多羽、明细与提交流程 -->
<!-- [POS]: Frontend core registration screen / 前端核心报名页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showDialog, showToast } from 'vant'
import { api } from '../api/client'
import { useRegistrationStore } from '../stores/registration'
import { yuan } from '../utils/money'
import { demoBootstrap } from '../utils/demoBootstrap'
import AmountBar from '../components/AmountBar.vue'
import SingleMatrix from '../components/SingleMatrix.vue'
import MultiGroupBuilder from '../components/MultiGroupBuilder.vue'
import SelectedDetail from '../components/SelectedDetail.vue'

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
  if (!store.race || store.submitEntries.length === 0) return
  await showDialog({
    title: '确认提交报名',
    message: `共 ${store.selectedCount} 注，总金额 ${yuan(store.totalAmountCent)}。提交后以后台校验金额为准。`,
    showCancelButton: true,
    confirmButtonText: '确认提交',
  })

  submitting.value = true
  try {
    const response = await api.post(`/api/member/races/${store.race.id}/registrations`, {
      config_version: store.race.config_version,
      idempotency_key: crypto.randomUUID(),
      entries: store.submitEntries,
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
        <h1>{{ store.race.name }}</h1>
        <div class="member-line">
          <span>棚号 {{ store.member.loft_number }}</span>
          <span>{{ store.member.participant_name }}</span>
          <span>我的赛鸽 {{ store.pigeons.length }} 羽</span>
        </div>
        <p>报名截止：{{ store.race.registration_end_at }}</p>
      </header>

      <nav class="tabs">
        <button :class="{ active: store.activeTab === 'single' }" @click="store.activeTab = 'single'">单羽组</button>
        <button :class="{ active: store.activeTab === 'multi' }" @click="store.activeTab = 'multi'">多羽组</button>
        <button :class="{ active: store.activeTab === 'detail' }" @click="store.activeTab = 'detail'">已选明细</button>
      </nav>

      <label class="search-box">
        <span>搜索足环号码</span>
        <input v-model="store.searchQuery" placeholder="支持完整号码、后 3/4/6 位或连续号码" />
      </label>

      <SingleMatrix v-if="store.activeTab === 'single'" />
      <MultiGroupBuilder v-else-if="store.activeTab === 'multi'" />
      <SelectedDetail v-else />

      <AmountBar
        :selected-count="store.selectedCount"
        :single-amount-cent="store.singleAmountCent"
        :multi-amount-cent="store.multiAmountCent"
        :total-amount-cent="store.totalAmountCent"
        @submit="confirmSubmit"
      />
      <div v-if="submitting" class="submit-mask">提交中，请勿重复点击</div>
    </template>
  </main>
</template>
