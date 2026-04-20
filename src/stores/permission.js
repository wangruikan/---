import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import request from '@/api/request'

export const usePermissionStore = defineStore('permission', () => {
  // 用户权限列表
  const permissions = ref([])
  // 是否是admin
  const isAdmin = ref(false)
  // 是否已加载
  const loaded = ref(false)

  /**
   * 加载当前用户的权限
   */
  const loadPermissions = async () => {
    try {
      console.log('[Permission] 开始加载权限...')
      const response = await request({
        url: '/permissions/my',
        method: 'get'
      })
      
      if (response.success) {
        // 确保 permissions 是数组格式
        const permissionsData = response.data.permissions || []
        // 如果是对象，转换为数组
        permissions.value = Array.isArray(permissionsData) 
          ? permissionsData 
          : Object.values(permissionsData)
        
        isAdmin.value = response.data.is_admin || false
        loaded.value = true
        console.log('[Permission] 权限加载成功:', {
          isAdmin: isAdmin.value,
          permissionCount: permissions.value.length,
          permissions: permissions.value
        })
      }
    } catch (error) {
      console.error('[Permission] 加载权限失败:', error)
      permissions.value = []
      isAdmin.value = false
    }
  }

  /**
   * 检查是否有某个权限
   * @param {string} permissionKey 权限标识，如：employees.view
   */
  const hasPermission = (permissionKey) => {
    // admin拥有所有权限
    if (isAdmin.value) {
      return true
    }
    // 确保 permissions.value 是数组，如果不是则转换
    let permArray = permissions.value
    if (!Array.isArray(permArray)) {
      console.warn('[Permission] permissions.value 不是数组，尝试转换:', permArray)
      // 如果是对象，转换为数组
      if (permArray && typeof permArray === 'object') {
        permArray = Object.values(permArray)
        // 同时更新 permissions.value
        permissions.value = permArray
      } else {
        return false
      }
    }
    const result = permArray.includes(permissionKey)
    // 调试：只在权限检查失败时输出日志
    if (!result && loaded.value) {
      console.log('[Permission] 权限检查:', permissionKey, '-> 无权限')
    }
    return result
  }

  /**
   * 检查是否有多个权限中的任意一个
   * @param {string[]} permissionKeys 权限标识数组
   */
  const hasAnyPermission = (permissionKeys) => {
    if (isAdmin.value) {
      return true
    }
    // 确保 permissions.value 是数组，如果不是则转换
    let permArray = permissions.value
    if (!Array.isArray(permArray)) {
      if (permArray && typeof permArray === 'object') {
        permArray = Object.values(permArray)
        permissions.value = permArray
      } else {
        return false
      }
    }
    return permissionKeys.some(key => permArray.includes(key))
  }

  /**
   * 检查是否拥有所有指定权限
   * @param {string[]} permissionKeys 权限标识数组
   */
  const hasAllPermissions = (permissionKeys) => {
    if (isAdmin.value) {
      return true
    }
    // 确保 permissions.value 是数组，如果不是则转换
    let permArray = permissions.value
    if (!Array.isArray(permArray)) {
      if (permArray && typeof permArray === 'object') {
        permArray = Object.values(permArray)
        permissions.value = permArray
      } else {
        return false
      }
    }
    return permissionKeys.every(key => permArray.includes(key))
  }

  /**
   * 检查是否有某个模块的任意权限
   * @param {string} module 模块名称，如：employees
   */
  const hasModuleAccess = (module) => {
    if (isAdmin.value) {
      return true
    }
    // 确保 permissions.value 是数组，如果不是则转换
    let permArray = permissions.value
    if (!Array.isArray(permArray)) {
      if (permArray && typeof permArray === 'object') {
        permArray = Object.values(permArray)
        permissions.value = permArray
      } else {
        return false
      }
    }
    return permArray.some(p => p.startsWith(module + '.'))
  }

  /**
   * 清空权限（用于退出登录）
   */
  const clearPermissions = () => {
    permissions.value = []
    isAdmin.value = false
    loaded.value = false
  }

  /**
   * 获取某个模块的所有权限
   * @param {string} module 模块名称
   */
  const getModulePermissions = (module) => {
    if (isAdmin.value) {
      // admin返回所有操作
      return ['view', 'create', 'edit', 'delete', 'approve', 'export']
    }
    // 确保 permissions.value 是数组，如果不是则转换
    let permArray = permissions.value
    if (!Array.isArray(permArray)) {
      if (permArray && typeof permArray === 'object') {
        permArray = Object.values(permArray)
        permissions.value = permArray
      } else {
        return []
      }
    }
    return permArray
      .filter(p => p.startsWith(module + '.'))
      .map(p => p.split('.')[1])
  }

  // 计算属性：是否可以查看员工
  const canViewEmployees = computed(() => hasPermission('employees.view'))
  const canCreateEmployees = computed(() => hasPermission('employees.create'))
  const canEditEmployees = computed(() => hasPermission('employees.edit'))
  const canDeleteEmployees = computed(() => hasPermission('employees.delete'))

  // 计算属性：是否可以查看项目
  const canViewProjects = computed(() => hasPermission('projects.view'))
  const canCreateProjects = computed(() => hasPermission('projects.create'))
  const canEditProjects = computed(() => hasPermission('projects.edit'))
  const canDeleteProjects = computed(() => hasPermission('projects.delete'))

  // 计算属性：是否可以查看合同
  const canViewContracts = computed(() => hasPermission('contracts.view'))
  const canCreateContracts = computed(() => hasPermission('contracts.create'))
  const canEditContracts = computed(() => hasPermission('contracts.edit'))
  const canDeleteContracts = computed(() => hasPermission('contracts.delete'))
  const canApproveContracts = computed(() => hasPermission('contracts.approve'))

  // 计算属性：是否可以查看发票申请
  const canViewInvoiceApplications = computed(() => hasPermission('invoice_applications.view'))
  const canCreateInvoiceApplications = computed(() => hasPermission('invoice_applications.create'))
  const canEditInvoiceApplications = computed(() => hasPermission('invoice_applications.edit'))
  const canDeleteInvoiceApplications = computed(() => hasPermission('invoice_applications.delete'))
  const canApproveInvoiceApplications = computed(() => hasPermission('invoice_applications.approve'))

  // 计算属性：是否可以管理权限
  const canManagePermissions = computed(() => isAdmin.value || hasPermission('permissions.edit'))

  return {
    // 状态
    permissions,
    isAdmin,
    loaded,
    
    // 方法
    loadPermissions,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    hasModuleAccess,
    clearPermissions,
    getModulePermissions,
    
    // 计算属性 - 员工
    canViewEmployees,
    canCreateEmployees,
    canEditEmployees,
    canDeleteEmployees,
    
    // 计算属性 - 项目
    canViewProjects,
    canCreateProjects,
    canEditProjects,
    canDeleteProjects,
    
    // 计算属性 - 合同
    canViewContracts,
    canCreateContracts,
    canEditContracts,
    canDeleteContracts,
    canApproveContracts,
    
    // 计算属性 - 发票申请
    canViewInvoiceApplications,
    canCreateInvoiceApplications,
    canEditInvoiceApplications,
    canDeleteInvoiceApplications,
    canApproveInvoiceApplications,
    
    // 计算属性 - 权限管理
    canManagePermissions
  }
})
