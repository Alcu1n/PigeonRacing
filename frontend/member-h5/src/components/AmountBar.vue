<!-- [IN]: Amount summary props / 金额汇总属性 -->
<!-- [OUT]: Fixed bottom amount and submit action bar / 固定底部金额与提交操作栏 -->
<!-- [POS]: Frontend amount summary component / 前端金额汇总组件 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { yuan } from '../utils/money'

defineProps<{
  selectedCount: number
  singleAmountCent: number
  multiAmountCent: number
  totalAmountCent: number
}>()

const emit = defineEmits<{ submit: [] }>()
</script>

<template>
  <footer class="amount-bar">
    <div class="amount-copy">
      <span>已选 {{ selectedCount }} 项</span>
      <strong>总计 {{ yuan(totalAmountCent) }}</strong>
      <small>单羽 {{ yuan(singleAmountCent) }} ｜ 多羽 {{ yuan(multiAmountCent) }}</small>
    </div>
    <button class="primary-action" :disabled="selectedCount === 0" @click="emit('submit')">查看明细并提交</button>
  </footer>
</template>
