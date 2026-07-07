<!-- [IN]: Registration store selected entries / 报名 Store 已选报名项目 -->
<!-- [OUT]: Grouped single, multi, and progressive detail preview / 分组单羽、多羽与递进阶段明细预览 -->
<!-- [POS]: Frontend selected detail component / 前端已选明细组件 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { computed } from 'vue'
import { useRegistrationStore } from '../stores/registration'
import { yuan } from '../utils/money'

const store = useRegistrationStore()

const singleGroups = computed(() => store.singleProjects.map((project) => {
  const entries = store.singleEntries.filter((entry) => entry.project_id === project.id)
  return { project, entries }
}).filter((group) => group.entries.length > 0))

const progressiveGroups = computed(() => store.progressiveCategories.map((category) => {
  const groups = store.progressiveSelections[category.id] ?? []
  return { category, groups }
}).filter((group) => group.groups.length > 0))
</script>

<template>
  <section class="detail-panel">
    <article v-for="group in singleGroups" :key="group.project.id" class="detail-block">
      <h3>{{ group.project.name }}</h3>
      <p>共 {{ group.entries.length }} 羽，小计 {{ yuan(group.entries.length * group.project.price_cent) }}</p>
      <ul>
        <li v-for="entry in group.entries.slice(0, 8)" :key="entry.project_id + '-' + entry.pigeon_ids[0]">
          {{ store.pigeonById(entry.pigeon_ids[0]).ring_number }}
        </li>
      </ul>
    </article>

    <article v-for="project in store.multiProjects" :key="project.id" class="detail-block">
      <template v-if="store.multiGroups.some((group) => group.project_id === project.id)">
        <h3>{{ project.name }}</h3>
        <p>共 {{ store.multiGroups.filter((group) => group.project_id === project.id).length }} 组，小计 {{ yuan(store.multiGroups.filter((group) => group.project_id === project.id).length * project.price_cent) }}</p>
        <div v-for="(group, index) in store.multiGroups.filter((item) => item.project_id === project.id)" :key="group.id" class="mini-group">
          <strong>第 {{ index + 1 }} 组</strong>
          <span>{{ group.pigeon_ids.map((id) => store.pigeonById(id).ring_number).join(' / ') }}</span>
        </div>
      </template>
    </article>

    <article v-for="group in progressiveGroups" :key="group.category.id" class="detail-block">
      <h3>{{ group.category.name }} · {{ group.category.current_stage?.name }}</h3>
      <p>共 {{ group.groups.length }} 组，小计 {{ yuan(group.groups.length * (group.category.current_stage?.price_cent ?? 0)) }}</p>
      <div v-for="(item, index) in group.groups.slice(0, 12)" :key="group.category.id + '-' + item.group_key" class="mini-group">
        <strong>第 {{ index + 1 }} 组</strong>
        <span>{{ item.pigeon_ids.map((id) => store.pigeonById(id).ring_number).join(' / ') }}</span>
      </div>
    </article>

    <p v-if="store.selectedCount === 0" class="empty-note">尚未选择报名项目</p>
  </section>
</template>
