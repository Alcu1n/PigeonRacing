<!-- [IN]: Registration store multi projects and pigeons / 报名 Store 的多羽项目与足环 -->
<!-- [OUT]: Multi-pigeon group builder and group cards / 多羽组合构建器与组合卡片 -->
<!-- [POS]: Frontend multi registration component / 前端多羽报名组件 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useRegistrationStore } from '../stores/registration'
import { yuan } from '../utils/money'

const store = useRegistrationStore()
const { multiProjects, selectedMultiProject, pendingMultiPigeonIds, filteredPigeons, multiGroups } = storeToRefs(store)
</script>

<template>
  <section class="multi-panel">
    <div class="project-switcher">
      <button
        v-for="project in multiProjects"
        :key="project.id"
        type="button"
        :class="{ active: selectedMultiProject?.id === project.id }"
        @click="store.setMultiProject(project.id)"
      >
        {{ project.name }}
      </button>
    </div>

    <div v-if="selectedMultiProject" class="multi-current">
      <strong>当前选择：{{ selectedMultiProject.name }}</strong>
      <span>请选择 {{ selectedMultiProject.group_size }} 只赛鸽组成一组，已选 {{ pendingMultiPigeonIds.length }}/{{ selectedMultiProject.group_size }}</span>
      <button class="secondary-action" :disabled="pendingMultiPigeonIds.length !== selectedMultiProject.group_size" @click="store.confirmMultiGroup">确认组成一组</button>
    </div>

    <div class="pigeon-pick-list">
      <button
        v-for="pigeon in filteredPigeons"
        :key="pigeon.id"
        type="button"
        class="pick-row"
        :class="{ selected: pendingMultiPigeonIds.includes(pigeon.id) }"
        @click="store.togglePendingMultiPigeon(pigeon.id)"
      >
        <span>{{ pigeon.ring_number }}</span>
        <strong>{{ pendingMultiPigeonIds.includes(pigeon.id) ? '已选' : '未选' }}</strong>
      </button>
    </div>

    <div class="group-list">
      <article v-for="(group, index) in multiGroups" :key="group.id" class="group-card">
        <header>
          <strong>{{ store.projectName(group.project_id) }} · 第 {{ index + 1 }} 组</strong>
          <span>{{ yuan(store.priceFor(group.project_id)) }}</span>
        </header>
        <ol>
          <li v-for="pigeonId in group.pigeon_ids" :key="pigeonId">{{ store.pigeonById(pigeonId).ring_number }}</li>
        </ol>
        <button type="button" @click="store.deleteMultiGroup(group.id)">删除此组</button>
      </article>
    </div>
  </section>
</template>
