import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { login, logout, getUserInfo } from '@/api/auth'
import { ElMessage } from 'element-plus'
import { useAccountSetStore } from './accountSet'

export const useUserStore = defineStore('user', () => {
  const token = ref(localStorage.getItem('token') || '')
  const userInfo = ref(null)
  const isLoggedIn = computed(() => !!token.value)

  const loginUser = async (credentials) => {
    try {
      console.log('[DEBUG][login] start login, username:', credentials?.username)
      const response = await login(credentials)
      console.log('[DEBUG][login] result from login():', response)
      console.log('[DEBUG][login] response.data (nested data):', response?.data)
      console.log('[DEBUG][login] response.data.token:', response?.data?.token)
      token.value = response.data.token
      userInfo.value = response.data.user
      localStorage.setItem('token', token.value)
      
      // 登录成功后加载账套
      try {
        const accountSetStore = useAccountSetStore()
        await accountSetStore.loadMyAccountSets()
      } catch (accountError) {
        console.error('加载账套失败:', accountError)
      }
      
      // 登录成功后加载权限
      try {
        const { usePermissionStore } = await import('./permission')
        const permissionStore = usePermissionStore()
        await permissionStore.loadPermissions()
      } catch (permError) {
        console.error('加载权限失败:', permError)
      }
      
      ElMessage.success('登录成功')
      return response
    } catch (error) {
      console.error('[DEBUG][login] login error:', error, 'error.response:', error?.response)
      ElMessage.error(error.response?.data?.message || '登录失败')
      throw error
    }
  }

  const logoutUser = async () => {
    try {
      await logout()
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      token.value = ''
      userInfo.value = null
      localStorage.removeItem('token')
      
      // 清除账套状态
      try {
        const accountSetStore = useAccountSetStore()
        accountSetStore.clearCurrentAccountSet()
      } catch (e) {
        console.error('清除账套状态失败:', e)
      }
      
      // 清除权限
      try {
        const { usePermissionStore } = await import('./permission')
        const permissionStore = usePermissionStore()
        permissionStore.clearPermissions()
      } catch (e) {
        console.error('清除权限失败:', e)
      }
      
      ElMessage.success('已退出登录')
    }
  }

  const initUser = async () => {
    if (token.value) {
      try {
        // 获取当前账套ID
        const accountSetStore = useAccountSetStore()
        const currentAccountSetId = accountSetStore.currentAccountSetId
        
        const response = await getUserInfo(currentAccountSetId)
        userInfo.value = response.data
        console.log('用户信息已初始化:', userInfo.value)
        
        // 页面刷新时也要加载权限
        try {
          const { usePermissionStore } = await import('./permission')
          const permissionStore = usePermissionStore()
          await permissionStore.loadPermissions()
        } catch (permError) {
          console.error('加载权限失败:', permError)
        }
      } catch (error) {
        console.error('获取用户信息失败，token 可能已过期:', error)
        // Token 无效，清除并跳转到登录页
        token.value = ''
        userInfo.value = null
        localStorage.removeItem('token')
      }
    }
  }

  const updateUserInfo = (info) => {
    userInfo.value = { ...userInfo.value, ...info }
  }

  const refreshUserInfo = async () => {
    if (token.value) {
      try {
        // 获取当前账套ID
        const accountSetStore = useAccountSetStore()
        const currentAccountSetId = accountSetStore.currentAccountSetId
        
        const response = await getUserInfo(currentAccountSetId)
        userInfo.value = response.data
        console.log('用户信息已刷新:', userInfo.value)
        return response
      } catch (error) {
        console.error('刷新用户信息失败:', error)
        throw error
      }
    }
  }

  return {
    token,
    userInfo,
    isLoggedIn,
    loginUser,
    logoutUser,
    initUser,
    updateUserInfo,
    refreshUserInfo
  }
})
