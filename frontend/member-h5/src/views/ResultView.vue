<!-- [IN]: Registration id route param and backend detail API / 报名 ID 路由参数与后端详情 API -->
<!-- [OUT]: Registration success detail screen with localized status and profile/logout actions / 带本地化状态和个人信息/退出动作的报名成功详情页面 -->
<!-- [POS]: Frontend registration result screen / 前端报名结果页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client'
import { yuan } from '../utils/money'
import { registrationStatusText, registrationStatusTone, type ExistingRegistration } from '../types/domain'
import MemberTopActions from '../components/MemberTopActions.vue'

const route = useRoute()
const router = useRouter()
const registration = ref<ExistingRegistration | null>(null)

onMounted(async () => {
  const response = await api.get(`/api/member/registrations/${route.params.registrationId}`)
  registration.value = response.data
})
</script>

<template>
  <main class="page result-page">
    <section v-if="registration" class="result-panel">
      <div class="result-title-row">
        <h1>报名提交成功</h1>
        <MemberTopActions />
      </div>
      <dl>
        <dt>报名编号</dt>
        <dd>{{ registration.registration_no }}</dd>
        <dt>报名时间</dt>
        <dd>{{ registration.submitted_at }}</dd>
        <dt>总金额</dt>
        <dd>{{ yuan(registration.total_amount_cent) }}</dd>
        <dt>状态</dt>
        <dd>
          <span :class="['registration-status-pill', registrationStatusTone(registration.status)]">
            {{ registrationStatusText(registration.status) }}
          </span>
        </dd>
      </dl>
      <button class="primary-action wide" @click="router.push('/races')">返回赛事列表</button>
    </section>
  </main>
</template>
