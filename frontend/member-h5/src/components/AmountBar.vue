<!-- [IN]: Selected count and total amount props / 已选数量与总金额属性 -->
<!-- [OUT]: Ultra-compact fixed bottom submit bar / 超紧凑固定底部提交栏 -->
<!-- [POS]: Frontend amount summary component / 前端金额汇总组件 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { yuan } from '../utils/money'

defineProps<{
  selectedCount: number
  totalAmountCent: number
}>()

const emit = defineEmits<{ submit: [] }>()
</script>

<template>
  <footer class="amount-bar">
    <div class="amount-copy">
      <span>已选 {{ selectedCount }} 项</span>
      <strong>总计 {{ yuan(totalAmountCent) }}</strong>
    </div>
    <button class="primary-action" :disabled="selectedCount === 0" @click="emit('submit')">查看明细并提交</button>
  </footer>
</template>
