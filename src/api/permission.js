import request from '@/api/request'

/**
 * 获取所有权限列表（按模块分组）
 */
export function getAllPermissions() {
  return request({
    url: '/permissions',
    method: 'get'
  })
}

/**
 * 获取当前用户的权限
 */
export function getMyPermissions() {
  return request({
    url: '/permissions/my',
    method: 'get'
  })
}

/**
 * 获取所有用户及其权限概览
 */
export function getUsersWithPermissions() {
  return request({
    url: '/permissions/users',
    method: 'get'
  })
}

/**
 * 获取指定用户的权限
 * @param {number} userId 用户ID
 */
export function getUserPermissions(userId) {
  return request({
    url: `/permissions/users/${userId}`,
    method: 'get'
  })
}

/**
 * 更新用户权限
 * @param {number} userId 用户ID
 * @param {number[]} permissionIds 权限ID数组
 */
export function updateUserPermissions(userId, permissionIds) {
  return request({
    url: `/permissions/users/${userId}`,
    method: 'put',
    data: { permission_ids: permissionIds }
  })
}

/**
 * 批量设置权限
 * @param {number[]} userIds 用户ID数组
 * @param {number[]} permissionIds 权限ID数组
 * @param {string} action 操作类型：add/remove/set
 */
export function batchSetPermissions(userIds, permissionIds, action) {
  return request({
    url: '/permissions/batch',
    method: 'post',
    data: {
      user_ids: userIds,
      permission_ids: permissionIds,
      action: action
    }
  })
}
