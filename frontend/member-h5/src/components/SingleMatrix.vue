<!-- [IN]: Registration store single projects and pigeons / 报名 Store 的单羽项目与足环 -->
<!-- [OUT]: Full-width compact single-pigeon matrix with row select-all / 带行全选的全宽紧凑单羽矩阵 -->
<!-- [POS]: Frontend single registration matrix component / 前端单羽报名矩阵组件 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useRegistrationStore } from '../stores/registration'
import { yuan } from '../utils/money'

const store = useRegistrationStore()
const { filteredPigeons, singleProjects, singleProjectStats, singleMatrix } = storeToRefs(store)

function copyRing(ringNumber: string): void {
  void globalThis.navigator?.clipboard?.writeText(ringNumber)
}

function projectHeadLines(name: string): [string, string] {
  const compact = name.replace(/\s+/g, '')
  return [compact.slice(0, 2), compact.slice(2)]
}
</script>

<template>
  <section class="single-panel">
    <div class="project-stats" aria-label="单羽项目统计">
      <div v-for="stat in singleProjectStats" :key="stat.project.id" class="stat-chip">
        <strong>{{ stat.project.name }}</strong>
        <span>{{ stat.count }} 羽 / {{ yuan(stat.amount_cent) }}</span>
      </div>
    </div>

    <div class="matrix-wrap single-matrix-wrap">
      <div class="matrix-grid header-row" :style="{ '--project-count': singleProjects.length }">
        <div class="select-all-cell sticky-select-cell">全选</div>
        <div class="ring-cell sticky-ring-cell">足环</div>
        <div v-for="project in singleProjects" :key="project.id" class="project-head">
          <span>{{ projectHeadLines(project.name)[0] }}</span>
          <span>{{ projectHeadLines(project.name)[1] }}</span>
        </div>
      </div>
      <div
        v-for="(pigeon, index) in filteredPigeons"
        :key="pigeon.id"
        class="matrix-grid body-row"
        :class="{ zebra: index % 2 === 1 }"
        :style="{ '--project-count': singleProjects.length }"
      >
        <button
          class="select-all-cell sticky-select-cell select-all-button"
          type="button"
          :class="{ selected: store.isSingleRowAllSelected(pigeon.id) }"
          @click="store.toggleSingleRowAll(pigeon.id)"
        >
          <span>{{ store.isSingleRowAllSelected(pigeon.id) ? '✓' : '' }}</span>
        </button>
        <button class="ring-cell sticky-ring-cell ring-button" type="button" @click="copyRing(pigeon.ring_number)">
          <span>{{ pigeon.ring_number.slice(0, -6) }}</span><strong>{{ pigeon.ring_number.slice(-6) }}</strong>
        </button>
        <button
          v-for="project in singleProjects"
          :key="project.id"
          type="button"
          class="matrix-toggle"
          :class="{ selected: singleMatrix[pigeon.id]?.[project.id] }"
          @click="store.toggleSingle(pigeon.id, project.id)"
        >
          <span v-if="singleMatrix[pigeon.id]?.[project.id]">✅</span>
          <span v-else>○</span>
        </button>
      </div>
    </div>
  </section>
</template>
