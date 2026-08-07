<!-- [IN]: Public information list API / 公开信息发布列表 API -->
<!-- [OUT]: Polished unauthenticated information list page / 视觉优化后的无需登录信息发布列表页 -->
<!-- [POS]: Frontend public information list screen / 前端公开信息列表页面 -->
<!-- Protocol: When updating me, sync this header + parent folder's .folder.md -->
<!-- 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md -->
<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import type { InformationCategory, InformationPostListItem } from '../types/domain'
import { informationCategoryLabel } from '../utils/information'
import { setPageTitle } from '../utils/pageTitle'
import { t } from '../i18n'

const router = useRouter()
const activeCategory = ref<InformationCategory | 'all'>('all')
const items = ref<InformationPostListItem[]>([])
const loading = ref(false)
const error = ref('')

const categories: Array<{ value: InformationCategory | 'all'; label: string }> = [
  { value: 'all', label: t('全部') },
  { value: 'rules', label: t('赛事规程') },
  { value: 'results', label: t('成绩发布') },
  { value: 'notice', label: t('通知公告') },
]

const emptyText = computed(() => error.value || (loading.value ? t('加载中...') : t('暂无发布内容')))
const latestPublishedAt = computed(() => items.value[0]?.published_at ? formatTime(items.value[0].published_at) : t('等待发布'))

onMounted(() => {
  setPageTitle(t('信息发布'))
  void load()
})

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const params = activeCategory.value === 'all' ? {} : { category: activeCategory.value }
    const response = await api.get('/api/public/information', { params })
    items.value = response.data.items ?? []
  } catch {
    error.value = t('信息加载失败，请稍后重试')
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
  return value ? value.replace('T', ' ').slice(0, 16) : t('暂未设置时间')
}

function categoryClass(category: InformationCategory): string {
  return `information-list-category-${category}`
}
</script>

<template>
  <main class="information-page information-list-page">
    <header class="information-list-hero">
      <div class="information-list-hero-top">
        <p>{{ t('协会 / 俱乐部') }}</p>
        <button class="information-list-back" @click="router.push('/login')">{{ t('返回登录') }}</button>
      </div>

      <div class="information-list-hero-title">
        <span>{{ t('公开信息中心') }}</span>
        <h1>{{ t('信息发布') }}</h1>
        <p>{{ t('赛事规程、成绩发布、通知公告') }}</p>
      </div>

      <div class="information-list-stats" :aria-label="t('信息发布概览')">
        <span>
          <b>{{ items.length }}</b>
          <small>{{ t('当前条目') }}</small>
        </span>
        <span>
          <b>{{ latestPublishedAt }}</b>
          <small>{{ t('最近更新') }}</small>
        </span>
      </div>
    </header>

    <nav class="information-list-tabs" :aria-label="t('信息分类')">
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
      <button v-for="post in items" :key="post.id" class="information-list-card" @click="openPost(post)">
        <span class="information-list-card-meta">
          <b :class="categoryClass(post.category)">{{ informationCategoryLabel(post.category) }}</b>
          <em v-if="post.is_pinned">{{ t('置顶') }}</em>
          <time>{{ formatTime(post.published_at) }}</time>
        </span>
        <strong>{{ post.title }}</strong>
        <small v-if="post.summary">{{ post.summary }}</small>
        <span class="information-list-card-action">{{ t('查看详情') }}</span>
      </button>
    </section>

    <section v-else-if="loading" class="information-list-skeleton" :aria-label="t('信息加载中')">
      <span></span>
      <span></span>
    </section>

    <p v-else class="information-list-empty">{{ emptyText }}</p>
  </main>
</template>
