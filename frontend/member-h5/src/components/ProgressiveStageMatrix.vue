<!-- [IN]: Active progressive category from registration store / 报名 Store 当前递进类别 -->
<!-- [OUT]: Current-stage single-column eligibility matrix with star selected markers / 当前阶段单列资格矩阵与星标选中态 -->
<!-- [POS]: Frontend progressive stage registration component / 前端递进阶段报名组件 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import starIcon from '../assets/star.svg'
import { useRegistrationStore } from '../stores/registration'
import { yuan } from '../utils/money'

const store = useRegistrationStore()
const { activeProgressiveCategory, searchQuery } = storeToRefs(store)

const eligiblePigeons = computed(() => {
  const category = activeProgressiveCategory.value
  if (!category) return []
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return category.eligible_pigeons

  return category.eligible_pigeons.filter((pigeon) => pigeon.ring_number.toLowerCase().includes(query))
})

const selectedCount = computed(() => {
  const category = activeProgressiveCategory.value
  if (!category) return 0

  return store.progressiveSelections[category.id]?.length ?? 0
})

function copyRing(ringNumber: string): void {
  void globalThis.navigator?.clipboard?.writeText(ringNumber)
}
</script>

<template>
  <section v-if="activeProgressiveCategory" class="progressive-panel">
    <div class="progressive-current">
      <div>
        <strong>{{ activeProgressiveCategory.name }}</strong>
        <span>{{ activeProgressiveCategory.current_stage?.name ?? '未配置当前阶段' }}</span>
      </div>
      <div>
        <b>{{ selectedCount }}</b>
        <span>羽 / {{ yuan(selectedCount * (activeProgressiveCategory.current_stage?.price_cent ?? 0)) }}</span>
      </div>
    </div>

    <p v-if="!activeProgressiveCategory.current_stage" class="empty-note">后台尚未配置当前开放阶段。</p>
    <p v-else-if="activeProgressiveCategory.eligible_pigeons.length === 0" class="empty-note">暂无上一阶段已确认足环。</p>
    <div v-else class="matrix-wrap progressive-matrix-wrap">
      <div class="progressive-grid header-row">
        <div class="ring-cell sticky-ring-cell">足环</div>
        <div class="project-head">
          <span>{{ activeProgressiveCategory.current_stage.name }}</span>
          <small>{{ yuan(activeProgressiveCategory.current_stage.price_cent) }}</small>
        </div>
      </div>
      <div
        v-for="(pigeon, index) in eligiblePigeons"
        :key="pigeon.id"
        class="progressive-grid body-row"
        :class="{ zebra: index % 2 === 1 }"
      >
        <button class="ring-cell sticky-ring-cell ring-button" type="button" @click="copyRing(pigeon.ring_number)">
          <span>{{ pigeon.ring_number.slice(0, -6) }}</span><strong>{{ pigeon.ring_number.slice(-6) }}</strong>
        </button>
        <button
          type="button"
          class="matrix-toggle"
          :class="{ selected: store.isProgressivePigeonSelected(activeProgressiveCategory.id, pigeon.id) }"
          @click="store.toggleProgressivePigeon(activeProgressiveCategory.id, pigeon.id)"
        >
          <img
            v-if="store.isProgressivePigeonSelected(activeProgressiveCategory.id, pigeon.id)"
            class="matrix-selected-icon"
            :src="starIcon"
            alt=""
            aria-hidden="true"
          />
          <span v-else>○</span>
        </button>
      </div>
    </div>
  </section>
</template>
