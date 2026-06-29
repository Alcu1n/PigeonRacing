// [IN]: Vite command, Vue source files, and optional CDN asset base env / Vite 命令、Vue 源码文件与可选 CDN 资源基址环境变量
// [OUT]: Member H5 build, dev server, and static asset base config / 会员 H5 构建、开发服务与静态资源基址配置
// [POS]: Frontend Vite configuration / 前端 Vite 配置
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

function normalizeAssetBase(baseUrl?: string): string {
  const value = baseUrl?.trim()
  if (!value) return '/'

  return value.endsWith('/') ? value : `${value}/`
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    base: normalizeAssetBase(env.VITE_ASSET_BASE_URL),
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
  }
})
