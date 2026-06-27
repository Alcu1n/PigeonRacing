<!-- [IN]: Member auth store and router / 会员鉴权 Store 与路由 -->
<!-- [OUT]: Compact profile and logout actions for authenticated screens / 已登录页面的紧凑个人信息与退出动作 -->
<!-- [POS]: Frontend shared member session action group / 前端共享会员会话动作组 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { useRouter } from 'vue-router'
import MemberLogoutButton from './MemberLogoutButton.vue'

const router = useRouter()
</script>

<template>
  <div class="member-actions">
    <button class="profile-action" type="button" @click="router.push('/profile')">个人信息</button>
    <MemberLogoutButton />
  </div>
</template>
