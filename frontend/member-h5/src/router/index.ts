// [IN]: Vue route records and member auth store / Vue 路由记录与会员鉴权 Store
// [OUT]: Member H5 router instance with public information routes and first-login password guard / 带公开信息路由与首次改密守卫的会员 H5 路由实例
// [POS]: Frontend route map / 前端路由地图
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../views/LoginView.vue'
import RaceListView from '../views/RaceListView.vue'
import RegistrationView from '../views/RegistrationView.vue'
import ResultView from '../views/ResultView.vue'
import ProfileView from '../views/ProfileView.vue'
import RegistrationHistoryDetailView from '../views/RegistrationHistoryDetailView.vue'
import InformationListView from '../views/InformationListView.vue'
import InformationDetailView from '../views/InformationDetailView.vue'
import { useAuthStore } from '../stores/auth'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/login' },
    { path: '/login', component: LoginView },
    { path: '/information', component: InformationListView },
    { path: '/information/:slug', component: InformationDetailView },
    { path: '/infomation', redirect: '/information' },
    { path: '/profile', component: ProfileView },
    { path: '/profile/registrations/:registrationId', component: RegistrationHistoryDetailView },
    { path: '/races', component: RaceListView },
    { path: '/races/:raceId/register', component: RegistrationView },
    { path: '/registrations/:registrationId', component: ResultView },
  ],
})

router.beforeEach(async (to) => {
  if (isPublicPath(to.path)) return true

  const auth = useAuthStore()
  if (!auth.member) {
    try {
      await auth.fetchProfile()
    } catch {
      return '/login'
    }
  }

  if (auth.member?.must_change_password && to.path !== '/profile') {
    return { path: '/profile', query: { forcePassword: '1' } }
  }

  return true
})

function isPublicPath(path: string): boolean {
  return path === '/login' || path === '/infomation' || path === '/information' || path.startsWith('/information/')
}
