<!-- [IN]: Public information detail API and route slug / 公开信息详情 API 与路由 slug -->
<!-- [OUT]: Sanitized rich-text information detail page / 已清洗富文本信息详情页 -->
<!-- [POS]: Frontend public information detail screen / 前端公开信息详情页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client'
import type { InformationPostDetail } from '../types/domain'
import { informationCategoryLabel, sanitizeInformationHtml } from '../utils/information'
import { setPageTitle } from '../utils/pageTitle'

const route = useRoute()
const router = useRouter()
const post = ref<InformationPostDetail | null>(null)
const loading = ref(false)
const error = ref('')

const sanitizedHtml = computed(() => sanitizeInformationHtml(post.value?.content_html ?? ''))

onMounted(load)
onUnmounted(() => setPageTitle())

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get(`/api/public/information/${String(route.params.slug)}`)
    post.value = response.data.post ?? null
    setPageTitle(post.value?.title ?? '信息详情')
  } catch {
    error.value = '内容不存在或已下线'
    post.value = null
    setPageTitle('内容不存在')
  } finally {
    loading.value = false
  }
}

function formatTime(value?: string | null): string {
  return value ? value.replace('T', ' ').slice(0, 16) : ''
}
</script>

<template>
  <main class="information-page information-detail-page">
    <button class="information-back inline" @click="router.push('/information')">返回列表</button>

    <article v-if="post" class="information-detail">
      <header>
        <span class="information-card-meta">
          <b>{{ informationCategoryLabel(post.category) }}</b>
          <em v-if="post.is_pinned">置顶</em>
          <time>{{ formatTime(post.published_at) }}</time>
        </span>
        <h1>{{ post.title }}</h1>
        <p v-if="post.summary">{{ post.summary }}</p>
      </header>
      <section class="information-prose" v-html="sanitizedHtml"></section>
    </article>

    <p v-else class="information-empty">{{ loading ? '加载中...' : error }}</p>
  </main>
</template>
