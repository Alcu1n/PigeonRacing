<!-- [IN]: Authenticated member profile API and password form / 已鉴权会员档案 API 与改密表单 -->
<!-- [OUT]: Member profile, pigeon list, and password update workflow / 会员档案、足环列表与改密流程 -->
<!-- [POS]: Frontend member profile screen / 前端会员个人档案页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import MemberTopActions from '../components/MemberTopActions.vue'
import { useAuthStore } from '../stores/auth'
import type { Pigeon } from '../types/domain'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const pigeons = ref<Pigeon[]>([])
const loading = ref(true)
const submitting = ref(false)
const currentPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const forcePassword = computed(() => route.query.forcePassword === '1' || auth.member?.must_change_password)

onMounted(async () => {
  const profile = await auth.fetchProfile()
  pigeons.value = profile.pigeons
  loading.value = false
})

async function updatePassword(): Promise<void> {
  if (submitting.value) return

  submitting.value = true
  try {
    const profile = await auth.updatePassword(currentPassword.value, password.value, passwordConfirmation.value)
    pigeons.value = profile.pigeons
    currentPassword.value = ''
    password.value = ''
    passwordConfirmation.value = ''
    showToast('密码已修改')
    await router.replace('/races')
  } catch (error) {
    if (axios.isAxiosError(error)) {
      showToast(error.response?.data?.message ?? '修改密码失败')
      return
    }

    showToast('修改密码失败')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="page profile-page">
    <header class="page-header page-topbar">
      <div>
        <h1>个人信息</h1>
        <p>{{ forcePassword ? '首次登录请先修改密码' : '会员档案与名下足环' }}</p>
      </div>
      <MemberTopActions />
    </header>

    <p v-if="loading" class="empty-note">加载个人信息中...</p>
    <template v-else-if="auth.member">
      <section class="profile-card">
        <dl>
          <dt>棚号</dt>
          <dd>{{ auth.member.loft_number }}</dd>
          <dt>参赛名</dt>
          <dd>{{ auth.member.participant_name }}</dd>
          <dt>手机号</dt>
          <dd>{{ auth.member.phone || '未设置' }}</dd>
        </dl>
      </section>

      <section class="profile-card">
        <h2>修改密码</h2>
        <p v-if="forcePassword" class="force-note">当前账号必须先修改密码，完成后才能进入赛事报名。</p>
        <label>
          <span>当前密码</span>
          <input v-model="currentPassword" type="password" autocomplete="current-password" placeholder="请输入当前密码" />
        </label>
        <label>
          <span>新密码</span>
          <input v-model="password" type="password" autocomplete="new-password" placeholder="至少 6 位" />
        </label>
        <label>
          <span>确认新密码</span>
          <input v-model="passwordConfirmation" type="password" autocomplete="new-password" placeholder="请再次输入新密码" />
        </label>
        <button class="primary-action wide" :disabled="submitting" @click="updatePassword">
          {{ submitting ? '保存中...' : '保存新密码' }}
        </button>
      </section>

      <section class="profile-card">
        <h2>名下足环</h2>
        <p v-if="pigeons.length === 0" class="empty-note">暂无足环信息</p>
        <ul v-else class="profile-pigeon-list">
          <li v-for="pigeon in pigeons" :key="pigeon.id">{{ pigeon.ring_number }}</li>
        </ul>
      </section>

      <button v-if="!forcePassword" class="secondary-action wide" @click="router.push('/races')">返回赛事列表</button>
    </template>
  </main>
</template>
