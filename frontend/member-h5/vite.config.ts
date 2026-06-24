// [IN]: Vite command and Vue source files / Vite 命令与 Vue 源码文件
// [OUT]: Member H5 build and dev server config / 会员 H5 构建与开发服务配置
// [POS]: Frontend Vite configuration / 前端 Vite 配置
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    proxy: {
      '/api': 'http://localhost:8080',
      '/sanctum': 'http://localhost:8080',
    },
  },
  test: {
    environment: 'jsdom',
  },
})
