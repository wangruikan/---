import axios from 'axios'
import { ElMessage } from 'element-plus'
import { useUserStore } from '@/stores/user'

// 根据环境自动选择API地址
const getBaseURL = () => {
  const envBase = import.meta.env.VITE_API_BASE_URL
  if (envBase) {
    return envBase.endsWith('/api') ? envBase : `${envBase}/api`
  }
  if (import.meta.env.DEV) {
    return '/api'
  }
  return '/api'
}

// 创建axios实例
const request = axios.create({
  baseURL: getBaseURL(),
  timeout: 30000, // 上传文件需要更长时间，改为30秒
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// 请求拦截器
request.interceptors.request.use(
  (config) => {
    const userStore = useUserStore()
    if (userStore.token) {
      config.headers.Authorization = `Bearer ${userStore.token}`
      config.headers['X-Auth-Token'] = userStore.token
    }
    
    // 如果是FormData，删除默认的Content-Type，让浏览器自动设置
    if (config.data instanceof FormData) {
      delete config.headers['Content-Type']
    }
    
    // 【账套功能】自动添加当前账套ID到请求
    const currentAccountSetId = localStorage.getItem('current_account_set_id')
    
    if (currentAccountSetId) {
      // 转换为数字类型
      const accountSetIdNum = parseInt(currentAccountSetId, 10)

      config.headers['X-Account-Set-Id'] = accountSetIdNum

      // GET/DELETE 请求使用 params
      if (config.method === 'get' || config.method === 'delete') {
        config.params = {
          ...config.params,
          current_account_set_id: accountSetIdNum
        }
      } else {
        // POST/PUT 请求添加到 data 中
        if (config.data instanceof FormData) {
          // FormData 需要使用 append 方法
          config.data.append('current_account_set_id', accountSetIdNum)
        } else {
          // 普通对象使用展开运算符
          config.data = {
            ...(config.data || {}),  // 即使 data 为空也创建对象
            current_account_set_id: accountSetIdNum
          }
        }
      }
    }
    
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// 响应拦截器
request.interceptors.response.use(
  async (response) => {
    // 如果是 blob 响应（文件下载）
    if (response.config.responseType === 'blob') {
      const blobType = (response.data?.type || '').toLowerCase()

      // 检查是否是错误响应（JSON格式）
      if (
        blobType.includes('application/json') ||
        blobType.includes('text/html')
      ) {
        const text = await response.data.text()
        try {
          const errorData = JSON.parse(text)
          ElMessage.error(errorData.message || '下载失败')
          return Promise.reject(new Error(errorData.message || '下载失败'))
        } catch (e) {
          const fallbackMsg = text && text.length < 200 ? text : '下载失败'
          ElMessage.error(fallbackMsg)
          return Promise.reject(new Error(fallbackMsg))
        }
      }
      // 正常的文件 blob
      return response.data
    }
    
    const { data } = response
    
    // 如果后端返回的数据结构是 { success: true, data: ... }
    if (data.success === false) {
      ElMessage.error(data.message || '请求失败')
      return Promise.reject(new Error(data.message || '请求失败'))
    }
    
    return data
  },
  (error) => {
    const { response } = error
    
    if (response) {
      const { status, data } = response
      
      switch (status) {
        case 401:
          ElMessage.error('未授权，请重新登录')
          const userStore = useUserStore()
          // 清除 token 并跳转到登录页
          userStore.token = ''
          userStore.userInfo = null
          localStorage.removeItem('token')
          // 延迟跳转，避免在某些情况下立即跳转造成问题
          setTimeout(() => {
            window.location.href = '/login'
          }, 1000)
          break
        case 403:
          ElMessage.error('拒绝访问')
          break
        case 404:
          ElMessage.error('请求的资源不存在')
          break
        case 422:
          ElMessage.error(data.message || '数据验证失败')
          break
        case 500:
          ElMessage.error('服务器内部错误')
          break
        default:
          ElMessage.error(data.message || '请求失败')
      }
    } else {
      ElMessage.error('网络错误，请检查网络连接')
    }
    
    return Promise.reject(error)
  }
)

export default request
