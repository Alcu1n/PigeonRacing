<!-- [IN]: Public information list API / 公开信息发布列表 API -->
<!-- [OUT]: Unauthenticated information list page / 无需登录的信息发布列表页 -->
<!-- [POS]: Frontend public information list screen / 前端公开信息列表页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import type { InformationCategory, InformationPostListItem } from '../types/domain'
import { informationCategoryLabel } from '../utils/information'

const router = useRouter()
const activeCategory = ref<InformationCategory | 'all'>('all')
const items = ref<InformationPostListItem[]>([])
const loading = ref(false)
const error = ref('')

const categories: Array<{ value: InformationCategory | 'all'; label: string }> = [
  { value: 'all', label: '全部' },
  { value: 'rules', label: '赛事规程' },
  { value: 'results', label: '成绩发布' },
  { value: 'notice', label: '通知公告' },
]

const emptyText = computed(() => error.value || (loading.value ? '加载中...' : '暂无发布内容'))

onMounted(load)

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const params = activeCategory.value === 'all' ? {} : { category: activeCategory.value }
    const response = await api.get('/api/public/information', { params })
    items.value = response.data.items ?? []
  } catch {
    error.value = '信息加载失败，请稍后重试'
    items.value = []
  } finally {
    loading.value = false
  }
}

async function selectCategory(category: InformationCategory | 'all'): Promise<void> {
  if (activeCategory.value === category) return
  activeCategory.value = category
  await load()
}

function openPost(post: InformationPostListItem): void {
  router.push(`/information/${post.slug}`)
}

function formatTime(value?: string | null): string {
  return value ? value.replace('T', ' ').slice(0, 16) : '暂未设置时间'
}
</script>

<template>
  <main class="information-page">
    <header class="information-hero">
      <button class="information-back" @click="router.push('/login')">返回登录</button>
      <p>协会 / 俱乐部</p>
      <h1>信息发布</h1>
      <span>赛事规程、成绩发布、通知公告</span>
    </header>

    <nav class="information-tabs" aria-label="信息分类">
      <button
        v-for="category in categories"
        :key="category.value"
        :class="{ active: activeCategory === category.value }"
        @click="selectCategory(category.value)"
      >
        {{ category.label }}
      </button>
    </nav>

    <section v-if="items.length" class="information-list">
      <button v-for="post in items" :key="post.id" class="information-card" @click="openPost(post)">
        <span class="information-card-meta">
          <b>{{ informationCategoryLabel(post.category) }}</b>
          <em v-if="post.is_pinned">置顶</em>
          <time>{{ formatTime(post.published_at) }}</time>
        </span>
        <strong>{{ post.title }}</strong>
        <small v-if="post.summary">{{ post.summary }}</small>
      </button>
    </section>

    <p v-else class="information-empty">{{ emptyText }}</p>
  </main>
</template>
