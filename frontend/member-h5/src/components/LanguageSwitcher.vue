<script setup lang="ts">
import { computed } from 'vue'
import { locale, setLocale, t, type FrontendLocale } from '../i18n'

const selected = computed({
  get: () => locale.value,
  set: (value: FrontendLocale) => {
    if (value === locale.value) return
    setLocale(value)
    window.location.reload()
  },
})
</script>

<template>
  <label class="language-switcher">
    <span class="sr-only">{{ t('语言') }}</span>
    <select v-model="selected" :aria-label="t('语言')">
      <option value="zh-CN">{{ t('简体中文') }}</option>
      <option value="zh-TW">{{ t('繁体中文') }}</option>
    </select>
  </label>
</template>
