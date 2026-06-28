<!-- [IN]: Member auth store, router, and optional return action prop / 会员鉴权 Store、路由与可选返回动作属性 -->
<!-- [OUT]: Compact profile, logout, and optional race-list return actions / 紧凑个人信息、退出与可选返回赛事列表动作 -->
<!-- [POS]: Frontend shared member session action group / 前端共享会员会话动作组 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { useRouter } from 'vue-router'
import MemberLogoutButton from './MemberLogoutButton.vue'

defineProps<{
  showRaceListReturn?: boolean
}>()

const router = useRouter()
</script>

<template>
  <div class="member-actions-panel">
    <div class="member-actions">
      <button class="profile-action" type="button" @click="router.push('/profile')">个人信息</button>
      <MemberLogoutButton />
    </div>
    <button
      v-if="showRaceListReturn"
      class="race-list-return-action"
      type="button"
      @click="router.push('/races')"
    >
      返回赛事列表
    </button>
  </div>
</template>
