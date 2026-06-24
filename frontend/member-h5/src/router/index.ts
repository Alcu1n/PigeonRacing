// [IN]: Vue route records / Vue 路由记录
// [OUT]: Member H5 router instance / 会员 H5 路由实例
// [POS]: Frontend route map / 前端路由地图
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md
import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../views/LoginView.vue'
import RaceListView from '../views/RaceListView.vue'
import RegistrationView from '../views/RegistrationView.vue'
import ResultView from '../views/ResultView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/login' },
    { path: '/login', component: LoginView },
    { path: '/races', component: RaceListView },
    { path: '/races/:raceId/register', component: RegistrationView },
    { path: '/registrations/:registrationId', component: ResultView },
  ],
})
