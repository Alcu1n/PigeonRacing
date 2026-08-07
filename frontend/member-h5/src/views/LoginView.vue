<!-- [IN]: Branding API plus blank member phone and password input / 品牌 API 与空白会员手机号、密码输入 -->
<!-- [OUT]: Polished logo login screen, public information entry, ICP filing link, footer contact, and race-list navigation / 视觉优化后的 Logo 登录页、公开信息入口、ICP备案链接、页脚联系信息与赛事列表导航 -->
<!-- [POS]: Frontend member login screen / 前端会员登录页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { showDialog, showToast } from 'vant'
import { useAuthStore } from '../stores/auth'
import { api } from '../api/client'
import { t } from '../i18n'
import { setPageTitle } from '../utils/pageTitle'
import LanguageSwitcher from '../components/LanguageSwitcher.vue'

const auth = useAuthStore()
const router = useRouter()
const phone = ref('')
const password = ref('')
const loading = ref(false)
const logoUrl = ref<string | null>(null)

onMounted(async () => {
  setPageTitle(t('赛鸽会员系统'))

  try {
    const response = await api.get('/api/member/branding')
    logoUrl.value = response.data.logo_url ?? null
  } catch {
    logoUrl.value = null
  }
})

async function submit(): Promise<void> {
  loading.value = true
  try {
    await auth.login(phone.value, password.value)
    if (auth.member?.must_change_password) {
      await showDialog({
        title: t('请先修改密码'),
        message: t('为了账号安全，首次登录需要先修改密码。'),
        confirmButtonText: t('去修改密码'),
      })
      await router.replace('/profile?forcePassword=1')
      return
    }

    await router.push('/races')
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 422) {
      showToast(t('手机号或密码错误'))
      return
    }

    showToast(t('登录会话已失效，请刷新后重试'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login-screen">
    <LanguageSwitcher />
    <div class="login-main">
      <section class="login-panel">
        <div class="login-brand">
          <img v-if="logoUrl" class="login-logo" :src="logoUrl" :alt="t('赛鸽会员系统')" @error="logoUrl = null" />
          <h1>{{ t('会员登陆') }}</h1>
          <p>{{ t('赛事报名、会员信息、信息查看') }}</p>
        </div>
        <label>
          <span>{{ t('手机号') }}</span>
          <input v-model="phone" inputmode="tel" autocomplete="username" :placeholder="t('请输入会员手机号')" />
        </label>
        <label>
          <span>{{ t('密码') }}</span>
          <input v-model="password" type="password" autocomplete="current-password" :placeholder="t('请输入登录密码')" />
        </label>
        <button class="primary-action wide" :disabled="loading" @click="submit">{{ loading ? t('登录中...') : t('登录') }}</button>
      </section>

      <RouterLink class="information-entry" to="/information">
        <span class="information-entry-copy">
          <strong>{{ t('信息发布页面') }}</strong>
          <span>{{ t('协会/俱乐部 赛事规程、成绩发布、通知公告入口') }}</span>
        </span>
        <span class="information-entry-action">{{ t('进入') }}</span>
      </RouterLink>
    </div>

    <footer class="login-footer">
      <span>{{ t('© 飞乐赛鸽 2026') }} {{ t('联系电话：') }}18650024626</span>
      <span>{{ t('开发 微信：') }}lemonrere</span>
      <span>
        <a class="filing-record-link" href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer">
          闽ICP备2026029044号-1
        </a>
      </span>
    </footer>
  </main>
</template>
