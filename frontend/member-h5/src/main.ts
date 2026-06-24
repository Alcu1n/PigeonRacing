// [IN]: Vue runtime, router, Pinia, and styles / Vue 运行时、路由、Pinia 与样式
// [OUT]: Mounted member H5 app / 已挂载会员 H5 应用
// [POS]: Frontend application bootstrap / 前端应用启动
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import 'vant/lib/index.css'
import './style.css'
import App from './App.vue'
import { router } from './router'

createApp(App).use(createPinia()).use(router).mount('#app')
