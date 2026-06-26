// [IN]: Vue runtime, router, Pinia, styles, and mobile gesture events / Vue 运行时、路由、Pinia、样式与移动端手势事件
// [OUT]: Mounted member H5 app with zoom gestures blocked / 已挂载且阻止缩放手势的会员 H5 应用
// [POS]: Frontend application bootstrap / 前端应用启动
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import 'vant/lib/index.css'
import './style.css'
import App from './App.vue'
import { router } from './router'

document.addEventListener('gesturestart', (event) => event.preventDefault())
document.addEventListener('gesturechange', (event) => event.preventDefault())
document.addEventListener('gestureend', (event) => event.preventDefault())

createApp(App).use(createPinia()).use(router).mount('#app')
