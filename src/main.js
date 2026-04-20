import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import 'element-plus/dist/index.css'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'
import zhCn from 'element-plus/dist/locale/zh-cn.mjs'

import App from './App.vue'
import router from './router'
import './style.css'
import { useUserStore } from '@/stores/user'
import dateTimeDirective from '@/directives/dateTime'

const app = createApp(App)

// 注册所有图标
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component)
}

const pinia = createPinia()
app.use(pinia)
app.use(router)
app.use(ElementPlus, {
  locale: zhCn,
})

// 注册全局指令
app.directive('date-time', dateTimeDirective)

app.mount('#app')

// 应用挂载后，初始化用户信息（异步执行，不阻塞应用启动）
;(async () => {
  try {
    const userStore = useUserStore()
    const { useAccountSetStore } = await import('@/stores/accountSet')
    const { usePermissionStore } = await import('@/stores/permission')
    const accountSetStore = useAccountSetStore()
    const permissionStore = usePermissionStore()

    // 初始化用户信息
    await userStore.initUser()

    // 如果用户已登录，初始化账套信息和权限
    if (userStore.isLoggedIn) {
      await Promise.all([
        accountSetStore.loadMyAccountSets(),
        permissionStore.loadPermissions()
      ])
    }
  } catch (error) {
    console.error('初始化用户信息失败:', error)
  }
})()
