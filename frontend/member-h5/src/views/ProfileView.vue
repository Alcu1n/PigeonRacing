<!-- [IN]: Authenticated member profile, registration history API, and password form / 已鉴权会员档案、报名历史 API 与改密表单 -->
<!-- [OUT]: Redesigned member profile with compact hero, profile/password cards, downloadable registration history, and pigeon chips / 重设计会员档案页：紧凑 Hero、档案/改密卡片、可下载报名历史与足环芯片 -->
<!-- [POS]: Frontend member profile screen / 前端会员个人档案页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import MemberLogoutButton from '../components/MemberLogoutButton.vue'
import RegistrationReceiptDownload from '../components/RegistrationReceiptDownload.vue'
import { useAuthStore } from '../stores/auth'
import { registrationStatusText, registrationStatusTone, type Pigeon, type PigeonLibrary, type RegistrationHistoryItem } from '../types/domain'
import { api } from '../api/client'
import { yuan } from '../utils/money'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const pigeons = ref<Pigeon[]>([])
const pigeonLibraries = ref<PigeonLibrary[]>([])
const activeLibraryId = ref<number | null>(null)
const registrations = ref<RegistrationHistoryItem[]>([])
const loading = ref(true)
const submitting = ref(false)
const password = ref('')
const passwordConfirmation = ref('')
const forcePassword = computed(() => route.query.forcePassword === '1' || auth.member?.must_change_password)

onMounted(async () => {
  try {
    const profile = await auth.fetchProfile()
    pigeons.value = profile.pigeons
    setPigeonLibraries(profile.pigeon_libraries ?? [])
    await loadRegistrationHistory()
  } finally {
    loading.value = false
  }
})

async function loadRegistrationHistory(): Promise<void> {
  if (forcePassword.value) return

  try {
    const response = await api.get('/api/member/registrations')
    registrations.value = response.data
  } catch {
    registrations.value = []
    showToast('报名记录加载失败')
  }
}

async function updatePassword(): Promise<void> {
  if (submitting.value) return

  submitting.value = true
  try {
    const profile = await auth.updatePassword(password.value, passwordConfirmation.value)
    pigeons.value = profile.pigeons
    setPigeonLibraries(profile.pigeon_libraries ?? [])
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

function setPigeonLibraries(libraries: PigeonLibrary[]): void {
  pigeonLibraries.value = libraries
  activeLibraryId.value = libraries[0]?.id ?? null
}

const activeLibrary = computed(() => pigeonLibraries.value.find((library) => library.id === activeLibraryId.value) ?? pigeonLibraries.value[0] ?? null)
const visiblePigeons = computed(() => activeLibrary.value?.pigeons ?? pigeons.value)
</script>

<template>
  <main class="page profile-page">
    <header class="member-hero compact">
      <div class="member-hero-top">
        <button v-if="!forcePassword" class="member-hero-back" type="button" @click="router.push('/races')">
          返回赛事
        </button>
        <span v-else class="member-hero-brand">赛鸽会员系统</span>
        <MemberLogoutButton class="member-hero-logout" />
      </div>
      <div class="member-hero-identity">
        <span class="member-avatar large">{{ (auth.member?.participant_name || '会').slice(0, 1) }}</span>
        <div class="member-hero-title">
          <p>{{ forcePassword ? '首次登录请先修改密码' : '会员档案与名下足环' }}</p>
          <h1>{{ auth.member?.participant_name || '会员' }}</h1>
          <span>棚号 {{ auth.member?.loft_number || '-' }} · {{ auth.member?.phone || '未设置手机号' }}</span>
        </div>
      </div>
    </header>

    <p v-if="loading" class="empty-note">加载个人信息中...</p>
    <template v-else-if="auth.member">
      <div class="profile-grid">
        <section class="profile-card">
          <div class="profile-section-head">
            <h2>会员档案</h2>
          </div>
          <dl class="member-rows">
            <div>
              <dt>棚号</dt>
              <dd>{{ auth.member.loft_number }}</dd>
            </div>
            <div>
              <dt>参赛名</dt>
              <dd>{{ auth.member.participant_name }}</dd>
            </div>
            <div>
              <dt>手机号</dt>
              <dd>{{ auth.member.phone || '未设置' }}</dd>
            </div>
          </dl>
        </section>

        <section class="profile-card">
          <div class="profile-section-head">
            <h2>修改密码</h2>
          </div>
          <p v-if="forcePassword" class="force-note">当前账号必须先修改密码，完成后才能进入赛事报名。</p>
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
      </div>

      <section v-if="!forcePassword" class="profile-card">
        <div class="profile-section-head">
          <h2>报名记录</h2>
          <span v-if="registrations.length > 0" class="profile-section-badge">{{ registrations.length }} 条</span>
        </div>
        <p v-if="registrations.length === 0" class="empty-note">暂无报名记录</p>
        <div v-else class="history-list">
          <article
            v-for="item in registrations"
            :key="item.registration_id"
            class="history-list-item"
          >
            <div class="history-list-copy">
              <div class="history-list-title-row">
                <strong>{{ item.race_name }}</strong>
                <b :class="['registration-status-pill', registrationStatusTone(item.status)]">
                  {{ registrationStatusText(item.status) }}
                </b>
              </div>
              <span>{{ item.submitted_at }} · 报名号 {{ item.registration_no }}</span>
              <small>{{ yuan(item.total_amount_cent) }} · 单羽 {{ item.single_count }} 项 · 多羽 {{ item.multi_group_count }} 组 · 递进 {{ item.progressive_count ?? 0 }} 组</small>
            </div>
            <div class="history-list-actions">
              <button type="button" class="history-detail-action" @click="router.push(`/profile/registrations/${item.registration_id}`)">查看明细</button>
              <RegistrationReceiptDownload compact :registration-id="item.registration_id" />
            </div>
          </article>
        </div>
      </section>

      <section class="profile-card profile-pigeon-card">
        <div class="profile-section-head">
          <h2>名下足环</h2>
          <span v-if="pigeons.length > 0" class="profile-section-badge">{{ visiblePigeons.length }} 羽</span>
        </div>
        <div v-if="pigeonLibraries.length > 0" class="profile-library-tabs">
          <button
            v-for="library in pigeonLibraries"
            :key="library.id"
            type="button"
            :class="{ active: activeLibraryId === library.id }"
            @click="activeLibraryId = library.id"
          >
            {{ library.name }} · {{ library.pigeon_count }}
          </button>
        </div>
        <p v-if="pigeons.length === 0" class="empty-note">暂无足环信息</p>
        <div v-else class="profile-pigeon-scroll">
          <ul class="profile-pigeon-list">
            <li v-for="pigeon in visiblePigeons" :key="pigeon.id">{{ pigeon.ring_number }}</li>
          </ul>
        </div>
      </section>
    </template>
  </main>
</template>
