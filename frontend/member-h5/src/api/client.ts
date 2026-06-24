// [IN]: Browser fetch context and backend API paths / 浏览器 fetch 上下文与后端 API 路径
// [OUT]: Axios client with Sanctum cookie support / 支持 Sanctum Cookie 的 Axios 客户端
// [POS]: Frontend HTTP client boundary / 前端 HTTP 客户端边界
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import axios from 'axios'

export const api = axios.create({
  baseURL: '/',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
  },
})

export async function ensureCsrf(): Promise<void> {
  await api.get('/sanctum/csrf-cookie')
}
